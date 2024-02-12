<?php

namespace Mittwald\Deployer\Recipes;

use Deployer\Host\Host;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\GetSshUser\GetSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsersRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\UpdateSshUser\UpdateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\UpdateSshUser\UpdateSshUserRequestBody;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\AuthenticationAlternative2;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\PublicKey;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\SshUser;
use Mittwald\Deployer\Error\UnexpectedResponseException;
use Mittwald\Deployer\Util\SSH\SSHConfig;
use Mittwald\Deployer\Util\SSH\SSHConfigRenderer;
use Mittwald\Deployer\Util\SSH\SSHHost;
use Mittwald\Deployer\Util\SSH\SSHPublicKey;
use function Deployer\{after,
    currentHost,
    has,
    info,
    run,
    runLocally,
    selectedHosts,
    set,
    Support\parse_home_dir,
    task};
use function Mittwald\Deployer\get_str;
use function Mittwald\Deployer\get_str_nullable;

class SSHUserRecipe
{
    public static function setup(): void
    {
        set('mittwald_ssh_username', 'deployer');

        set('mittwald_ssh_public_key', function (): string {
            if (has('mittwald_ssh_public_key_file')) {
                return BaseRecipe::getFilesystem()->read(parse_home_dir(get_str('mittwald_ssh_public_key_file')));
            }

            // Need to do this in case `ssh_copy_id` contains a tilde that needs to be expanded
            return runLocally('cat {{ssh_copy_id}}');
        });

        task('mittwald:sshconfig', function (): void {
            static::assertSSHConfig();
        })
            ->once()
            ->desc('Asserts that a local SSH configuration is present for the mittwald platform');

        task('mittwald:sshuser', function (): void {
            static::assertSSHUser();
        })
            ->desc('Asserts that the SSH user for the mittwald platform is configured correctly');

        task('mittwald:sshuser-ready', function (): void {
            static::assertSSHAvailable();
        })
            ->desc('Asserts that the SSH user for the mittwald platform is available');

        after('mittwald:sshuser', 'mittwald:sshconfig');
        after('mittwald:sshconfig', 'mittwald:sshuser-ready');
    }

    public static function assertSSHUser(): void
    {
        $app     = AppRecipe::getAppInstallation();
        $sshUser = self::lookupOrCreateSSHUser();

        $remoteUser = "{$sshUser->getUserName()}@{$app->getShortId()}";

        info("setting SSH user to <fg=magenta;options=bold>{$remoteUser}</>");

        currentHost()->set('remote_user', $remoteUser);
    }

    public static function assertSSHAvailable(): void
    {
        $remoteUser = get_str('remote_user');
        $backoff = 5;

        for ($attempts = 10; $attempts > 0; $attempts--) {
            try {
                run("/bin/true");
                break;
            } catch (\Exception $e) {
                info("SSH user <fg=magenta;options=bold>{$remoteUser}</> not yet available, retrying in {$backoff} seconds... ({$e->getMessage()})");
                sleep($backoff);
            }
        }
    }

    private static function lookupOrCreateSSHUser(): SshUser
    {
        $project = BaseRecipe::getProject();
        $existingUser = static::findExistingSSHUserByName($project);

        if ($existingUser !== null) {
            info("using existing SSH user <fg=magenta;options=bold>deployer</>");
            return static::assertSSHUserHasPublicKey($existingUser);
        }

        return static::createSSHUser($project);
    }

    private static function findExistingSSHUserByName(Project $project): SshUser|null
    {
        $client = BaseRecipe::getClient()->sSHSFTPUser();
        $username = get_str('mittwald_ssh_username');

        $sshUsersReq = new ListSshUsersRequest($project->getId());
        $sshUsers = $client->listSshUsers($sshUsersReq)->getBody();

        foreach ($sshUsers as $sshUser) {
            if ($sshUser->getDescription() === $username) {
                return $sshUser;
            }
        }

        return null;
    }

    private static function assertSSHUserHasPublicKey(SshUser $sshUser): SshUser
    {
        $sshPublicKey = SSHPublicKey::fromString(get_str('mittwald_ssh_public_key'));

        if (static::hasSSHUserPublicKey($sshUser, $sshPublicKey)) {
            info("SSH user <fg=magenta;options=bold>deployer</> already has the correct SSH public key");
            return $sshUser;
        }

        static::addPublicKeyToSSHUser($sshUser, $sshPublicKey);

        return static::getSSHUser($sshUser->getId());
    }

    private static function hasSSHUserPublicKey(SshUser $sshUser, SSHPublicKey $publicKey): bool
    {
        $existingPublicKeys = $sshUser->getPublicKeys() ?? [];
        foreach ($existingPublicKeys as $existingPublicKey) {
            if ($existingPublicKey->getKey() === $publicKey->publicKey) {
                return true;
            }
        }

        return false;
    }

    private static function addPublicKeyToSSHUser(SshUser $sshUser, SSHPublicKey $publicKey): void
    {
        $client        = BaseRecipe::getClient()->sSHSFTPUser();
        $newPublicKeys = [
            ...$sshUser->getPublicKeys() ?? [],
            new PublicKey("deployer", $publicKey->publicKey),
        ];

        $updateReq = new UpdateSshUserRequest(
            $sshUser->getId(),
            (new UpdateSshUserRequestBody())->withPublicKeys($newPublicKeys),
        );
        $client->updateSshUser($updateReq);
    }

    private static function getSSHUser(string $id): SshUser
    {
        $client = BaseRecipe::getClient()->sSHSFTPUser();

        return $client->getSshUser(new GetSshUserRequest($id))->getBody();
    }

    private static function createSSHUser(Project $project): SshUser
    {
        $client = BaseRecipe::getClient()->sSHSFTPUser();

        $sshPublicKey = SSHPublicKey::fromString(get_str('mittwald_ssh_public_key'));

        info("creating SSH user <fg=magenta;options=bold>deployer</>");
        info("using SSH public key <fg=magenta;options=bold>{$sshPublicKey->publicKey}</>");

        $createUserAuth = new AuthenticationAlternative2([
            new PublicKey("deployer", $sshPublicKey->publicKey),
        ]);

        $createUserReq = new CreateSshUserRequest($project->getId(), (new CreateSshUserRequestBody($createUserAuth, get_str('mittwald_ssh_username'))));

        return $client->createSshUser($createUserReq)->getBody();
    }

    public static function assertSSHConfig(): void
    {
        static::assertLocalSSHDirectory();

        $sshConfig = static::buildSSHConfigForSelectedHosts();

        $renderer = new SSHConfigRenderer($sshConfig);
        $renderer->renderToFile(BaseRecipe::getFilesystem());

        static::assertLocalSSHPrivateKey();

        foreach (selectedHosts() as $host) {
            if ($host->has('mittwald_internal_hostname')) {
                $host->set('config_file', $sshConfig->filename);
            }
        }
    }

    private static function buildSSHConfigForSelectedHosts(): SSHConfig
    {
        $sshConfig = new SSHConfig('./.mw-deployer/sshconfig');

        foreach (selectedHosts() as $host) {
            /** @var string|null $internal */
            $internal = $host->get('mittwald_internal_hostname');
            if ($internal === null) {
                continue;
            }

            $sshHost = new SSHHost(name: $host->getAlias() ?? $host->getHostname() ?? "unknown", hostname: $internal);
            $sshHost = $sshHost->withIdentityFile(static::determineSSHPrivateKeyForHost($host));

            $sshConfig = $sshConfig->withHost($sshHost);
        }

        return $sshConfig;
    }

    private static function determineSSHPrivateKeyForHost(Host $host): string
    {
        $privateKeyFile = get_str_nullable('mittwald_ssh_private_key_file');
        if (is_string($privateKeyFile)) {
            return $privateKeyFile;
        }

        $privateKeyContents = get_str_nullable('mittwald_ssh_private_key');
        if (is_string($privateKeyContents)) {
            return './.mw-deployer/id_rsa';
        }

        $publicKeyFile = get_str_nullable('ssh_copy_id');
        if (is_string($publicKeyFile)) {
            /** @var string $privateKeyFile */
            $privateKeyFile = str_replace('.pub', '', $publicKeyFile);

            return $privateKeyFile;
        }

        throw new \InvalidArgumentException('could not determine SSH private key for host; please set one of "mittwald_ssh_private_key_file", "mittwald_ssh_private_key", or "ssh_copy_id".');
    }

    private static function assertLocalSSHPrivateKey(): void
    {
        static::assertLocalSSHDirectory();

        if (has('mittwald_ssh_private_key')) {
            BaseRecipe::getFilesystem()->write('./.mw-deployer/id_rsa', get_str('mittwald_ssh_private_key'));
        }
    }

    private static function assertLocalSSHDirectory(): void
    {
        if (!is_dir('./.mw-deployer')) {
            BaseRecipe::getFilesystem()->createDirectory('./.mw-deployer');
        }
    }
}