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
use function Deployer\{get, has, info, runLocally, set};

class SSHUserRecipe
{
    public static function set()
    {
        set('mittwald_ssh_key', '~/.ssh/deployer');
    }

    public static function assertSSHUser(): SshUser
    {
        $client = BaseRecipe::getClient()->sSHSFTPUser();
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

        $sshPublicKey = (function(): string {
            if (has('mittwald_ssh_key_contents')) {
                return get('mittwald_ssh_key_contents');
            } else {
                // Need to do this in case `ssh_copy_id` contains a tilde that needs to be expanded
                return runLocally('cat {{ssh_copy_id}}');
            }
        })();

        info("creating SSH user <fg=magenta;options=bold>deployer</>");

        $createUserAuth = new AuthenticationAlternative2([
            new PublicKey("deployer", $sshPublicKey)
        ]);

        $createUserReq = new CreateSshUserRequest($project->getId(), (new CreateSshUserRequestBody($createUserAuth, 'deployer')));
        $createUserRes = $client->createSshUser($createUserReq);

        if (!$createUserRes instanceof CreateSshUser201Response) {
            throw new \Exception('could not create SSH user');
        }

        return $createUserRes->getBody();
    }
}