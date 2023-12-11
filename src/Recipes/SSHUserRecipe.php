<?php

namespace Mittwald\Deployer\Recipes;

use Deployer\Host\Host;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUser201Response;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsers200Response;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsersRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\AuthenticationAlternative2;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\PublicKey;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\SshUser;
use Mittwald\Deployer\Error\UnexpectedResponseException;
use Mittwald\Deployer\Util\SSH\SSHConfig;
use Mittwald\Deployer\Util\SSH\SSHConfigRenderer;
use Mittwald\Deployer\Util\SSH\SSHHost;
use function Deployer\{after, currentHost, has, info, parse, runLocally, selectedHosts, Support\parse_home_dir, task};
use function Mittwald\Deployer\get_str;
use function Mittwald\Deployer\get_str_nullable;

class SSHUserRecipe
{
    public static function setup(): void
    {
        task('mittwald:sshconfig', function (): void {
            static::assertSSHConfig();
        })
            ->once()
            ->desc('Asserts that a local SSH configuration is present for the mittwald platform');

        task('mittwald:sshuser', function (): void {
            static::assertSSHUser();
        })
            ->desc('Asserts that the SSH user for the mittwald platform is configured correctly');

        after('mittwald:sshuser', 'mittwald:sshconfig');

    }

    public static function assertSSHUser(): void
    {
        $app     = AppRecipe::getAppInstallation();
        $sshUser = self::lookupOrCreateSSHUser();

        $remoteUser = "{$sshUser->getUserName()}@app-{$app->getId()}";

        info("setting SSH user to <fg=magenta;options=bold>{$remoteUser}</>");

        currentHost()->set('remote_user', $remoteUser);
    }

    private static function lookupOrCreateSSHUser(): SshUser
    {
        $client  = BaseRecipe::getClient()->sSHSFTPUser();
        $project = BaseRecipe::getProject();

        $sshUsersReq = new ListSshUsersRequest($project->getId());
        $sshUsersRes = $client->listSshUsers($sshUsersReq);

        if (!$sshUsersRes instanceof ListSshUsers200Response) {
            throw new UnexpectedResponseException('could not list SSH users', $sshUsersRes);
        }

        $sshUsers = $sshUsersRes->getBody();
        foreach ($sshUsers as $sshUser) {
            if ($sshUser->getDescription() === 'deployer') {
                info("using existing SSH user <fg=magenta;options=bold>deployer</>");
                return $sshUser;
            }
        }

        $sshPublicKey = (function (): string {
            if (has('mittwald_ssh_public_key_file')) {
                return file_get_contents(parse_home_dir(get_str('mittwald_ssh_public_key_file')));
            } else if (has('mittwald_ssh_public_key')) {
                return get_str('mittwald_ssh_public_key');
            } else {
                // Need to do this in case `ssh_copy_id` contains a tilde that needs to be expanded
                return runLocally('cat {{ssh_copy_id}}');
            }
        })();

        $sshPublicKeyParts               = explode(" ", $sshPublicKey);
        $sshPublicKeyPartsWithoutComment = array_slice($sshPublicKeyParts, 0, 2);
        $sshPublicKeyWithoutComment      = implode(" ", $sshPublicKeyPartsWithoutComment);

        info("creating SSH user <fg=magenta;options=bold>deployer</>");
        info("using SSH public key <fg=magenta;options=bold>{$sshPublicKeyWithoutComment}</>");

        $createUserAuth = new AuthenticationAlternative2([
            new PublicKey("deployer", $sshPublicKeyWithoutComment),
        ]);

        $createUserReq = new CreateSshUserRequest($project->getId(), (new CreateSshUserRequestBody($createUserAuth, 'deployer')));
        $createUserRes = $client->createSshUser($createUserReq);

        if (!$createUserRes instanceof CreateSshUser201Response) {
            throw new UnexpectedResponseException('could not create SSH user', $createUserRes);
        }

        return $createUserRes->getBody();
    }

    public static function assertSSHConfig(): void
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

        static::assertLocalSSHDirectory();

        $renderer = new SSHConfigRenderer($sshConfig);
        $renderer->renderToFile();

        static::assertLocalSSHPrivateKey();

        foreach (selectedHosts() as $host) {
            if ($host->has('mittwald_internal_hostname')) {
                $host->set('config_file', $sshConfig->filename);
            }
        }
    }

    private static function determineSSHPrivateKeyForHost(Host $host): string {
        /** @var mixed $privateKeyFile */
        $privateKeyFile = $host->get('mittwald_ssh_private_key_file');
        if (is_string($privateKeyFile)) {
            return $privateKeyFile;
        }

        /** @var mixed $privateKeyContents */
        $privateKeyContents = $host->get('mittwald_ssh_private_key');
        if (is_string($privateKeyContents)) {
            return './.mw-deployer/id_rsa';
        }

        /** @var mixed $publicKeyFile */
        $publicKeyFile = $host->get('ssh_copy_id');
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
            file_put_contents('./.mw-deployer/id_rsa', get_str('mittwald_ssh_private_key'));
        }
    }

    private static function assertLocalSSHDirectory(): void
    {
        if (!is_dir('./.mw-deployer')) {
            mkdir('./.mw-deployer', permissions: 0755, recursive: true);
        }
    }
}