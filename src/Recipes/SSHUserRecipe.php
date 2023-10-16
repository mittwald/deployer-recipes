<?php

namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUser201Response;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsers200Response;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsersRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\AuthenticationAlternative2;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\PublicKey;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\SshUser;
use function Deployer\{after,
    currentHost,
    get,
    has,
    info,
    parse,
    runLocally,
    selectedHosts,
    Support\parse_home_dir,
    task,
    warning};

class SSHUserRecipe
{
    public static function setup()
    {
        task('mittwald:sshconfig', static::class . '::assertSSHConfig')
            ->once()
            ->desc('Asserts that a local SSH configuration is present for the mittwald platform');

        task('mittwald:sshuser', static::class . '::assertSSHUser')
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
            throw new \Exception('could not list SSH users');
        }

        $sshUsers = $sshUsersRes->getBody();
        foreach ($sshUsers as $sshUser) {
            if ($sshUser->getDescription() === 'deployer') {
                info("using existing SSH user <fg=magenta;options=bold>deployer</>");
                return $sshUser;
            }
        }

        if (has('mittwald_ssh_private_key')) {
            static::assertLocalSSHDirectory();
            file_put_contents('./.mw-deployer/id_rsa', get('mittwald_ssh_private_key'));
        }

        $sshPublicKey = (function (): string {
            if (has('mittwald_ssh_public_key_file')) {
                return file_get_contents(parse_home_dir(get('mittwald_ssh_public_key_file')));
            } else if (has('mittwald_ssh_public_key')) {
                return get('mittwald_ssh_public_key');
            } else {
                // Need to do this in case `ssh_copy_id` contains a tilde that needs to be expanded
                return runLocally('cat {{ssh_copy_id}}');
            }
        })();

        $sshPublicKeyParts = explode(" ", $sshPublicKey);
        $sshPublicKeyPartsWithoutComment = array_slice($sshPublicKeyParts, 0, 2);
        $sshPublicKeyWithoutComment = implode(" ", $sshPublicKeyPartsWithoutComment);

        info("creating SSH user <fg=magenta;options=bold>deployer</>");
        info("using SSH public key <fg=magenta;options=bold>{$sshPublicKeyWithoutComment}</>");

        $createUserAuth = new AuthenticationAlternative2([
            new PublicKey("deployer", $sshPublicKeyWithoutComment),
        ]);

        $createUserReq = new CreateSshUserRequest($project->getId(), (new CreateSshUserRequestBody($createUserAuth, 'deployer')));
        $createUserRes = $client->createSshUser($createUserReq);

        if (!$createUserRes instanceof CreateSshUser201Response) {
            warning("http request body: " . json_encode($createUserReq->getBody()->toJson()));
            warning("http response status: {$createUserRes->httpResponse->getStatusCode()}");
            warning("http response body: " . json_encode($createUserRes->getBody()->toJson()));
            throw new \Exception('could not create SSH user; received ' . $createUserRes->httpResponse->getStatusCode() . ' status.');
        }

        return $createUserRes->getBody();
    }

    public static function assertSSHConfig(): void
    {
        $config = "";

        foreach (selectedHosts() as $host) {
            if ($internal = $host->get('mittwald_internal_hostname')) {
                $name   = $host->getAlias() ?? $host->getHostname();
                $config .= "Host {$name}\n\tHostName {$internal}\nStrictHostKeyChecking accept-new\n";

                if (has('mittwald_ssh_private_key_file')) {
                    $config .= parse("\tIdentityFile {{mittwald_ssh_private_key_file}}\n");
                } else if (has('mittwald_ssh_private_key')) {
                    $config .= "\tIdentityFile ./.mw-deployer/id_rsa\n";
                } else {
                    $privateKeyFile = str_replace('.pub', '', get('ssh_copy_id'));
                    $config .= "\tIdentityFile {$privateKeyFile}\n";
                }

                $config .= "\n";
            }

        }

        static::assertLocalSSHDirectory();

        file_put_contents('./.mw-deployer/sshconfig', $config);

        foreach (selectedHosts() as $host) {
            if ($host->has('mittwald_internal_hostname')) {
                $host->set('config_file', './.mw-deployer/sshconfig');
            }
        }
    }

    private static function assertLocalSSHDirectory(): void
    {
        if (!is_dir('./.mw-deployer')) {
            mkdir('./.mw-deployer', permissions: 0755, recursive: true);
        }
    }

    private static function assertUserSSHDirectory(): void
    {
        $userSSHDir = parse_home_dir("~/.ssh");
        if (!is_dir($userSSHDir)) {
            mkdir($userSSHDir, permissions: 0755, recursive: true);
        }
    }
}