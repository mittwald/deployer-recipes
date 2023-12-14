<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Recipes;

use GuzzleHttp\Psr7\Response;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserCreatedResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsersOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\UpdateSshUser\UpdateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\App\AppInstallation;
use Mittwald\ApiClient\Generated\V2\Schemas\App\VersionStatus;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\ProjectReadinessStatus;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\AuthenticationAlternative2;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\PublicKey;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\SshUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;

#[CoversClass(SSHUserRecipe::class)]
class SSHUserRecipeTest extends TestCase
{
    private TestFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestFixture($this);
        $this->fixture->processRunner->expects($this->any())
            ->method('run')
            ->with($this->anything(), 'cat ~/.ssh/id_rsa.pub', $this->anything())
            ->willReturn('ssh-rsa FOOBAR test@local');

        AppRecipe::setup();
        SSHUserRecipe::setup();

        $this->fixture->client->app->expects($this->any())
            ->method('getAppinstallation')
            ->willReturnCallback(function (GetAppinstallationRequest $req): GetAppinstallationOKResponse {
                return new GetAppinstallationOKResponse(
                    (new AppInstallation(
                        'APP_ID',
                        new VersionStatus('1.0.0'),
                        'description',
                        false,
                        $req->getAppInstallationId(),
                        '/foo',
                        'a-XXXXXX',
                    ))
                        ->withProjectId('PROJECT_ID'),
                );
            });
        $this->fixture->client->project->expects($this->any())
            ->method('getProject')
            ->willReturnCallback(fn(GetProjectRequest $req): GetProjectOKResponse => new GetProjectOKResponse(
                new Project(
                    new \DateTime(),
                    'CUSTOMER_ID',
                    'Description',
                    ['Web' => '/html'],
                    true,
                    $req->getProjectId(),
                    true,
                    ProjectReadinessStatus::ready,
                    'p-XXXXXX',
                ),
            ));
    }

    public function testAssertSSHUserCreatesSSHUserWhenItDoesNotExist(): void
    {
        $this->fixture->client->sshSFTPUser->expects($this->once())
            ->method('listSshUsers')
            ->willReturn(new ListSshUsersOKResponse([]));
        $this->fixture->client->sshSFTPUser->expects($this->once())
            ->method('createSshUser')
            ->with(new Callback(function (CreateSshUserRequest $request): bool {
                $sshUser = $request->getBody();
                $auth    = $sshUser->getAuthentication();

                $this->assertEquals('deployer', $sshUser->getDescription());
                $this->assertInstanceOf(AuthenticationAlternative2::class, $auth);

                $publicKeys = $auth->getPublicKeys();

                $this->assertCount(1, $publicKeys);
                $this->assertEquals('ssh-rsa FOOBAR', $publicKeys[0]->getKey());
                $this->assertEquals('deployer', $publicKeys[0]->getComment());
                return true;
            }))
            ->willReturnCallback(function (CreateSshUserRequest $req): CreateSshUserCreatedResponse {
                return new CreateSshUserCreatedResponse(
                    (new SshUser(
                        new \DateTime(),
                        new \DateTime(),
                        $req->getBody()->getDescription(),
                        false,
                        'SSH_USER_ID',
                        $req->getProjectId(),
                        'ssh-YYYYYY'
                    )),
                );
            });

        SSHUserRecipe::assertSSHUser();
    }

    public function testAssertSSHUserUsesExistingSSHUser(): void
    {
        $this->fixture->client->sshSFTPUser->expects($this->once())
            ->method('listSshUsers')
            ->willReturn(new ListSshUsersOKResponse([
                (new SshUser(
                    new \DateTime(),
                    new \DateTime(),
                    'deployer',
                    false,
                    'SSH_USER_ID',
                    'PROJECT_ID',
                    'ssh-YYYYYY'
                ))->withPublicKeys([
                    new PublicKey('deployer', 'ssh-rsa FOOBAR'),
                ])
            ]));
        $this->fixture->client->sshSFTPUser->expects($this->never())
            ->method('createSshUser');
        $this->fixture->client->sshSFTPUser->expects($this->never())
            ->method('updateSshUser');

        SSHUserRecipe::assertSSHUser();
    }

    public function testAssertSSHUserUpdatesExistingPublicKeys(): void
    {
        $this->fixture->client->sshSFTPUser->expects($this->once())
            ->method('listSshUsers')
            ->willReturn(new ListSshUsersOKResponse([
                (new SshUser(
                    new \DateTime(),
                    new \DateTime(),
                    'deployer',
                    false,
                    'SSH_USER_ID',
                    'PROJECT_ID',
                    'ssh-YYYYYY'
                ))->withPublicKeys([
                    new PublicKey('deployer', 'ssh-rsa BAR'),
                ])
            ]));
        $this->fixture->client->sshSFTPUser->expects($this->never())
            ->method('createSshUser');
        $this->fixture->client->sshSFTPUser->expects($this->once())
            ->method('updateSshUser')
            ->with(new Callback(function (UpdateSshUserRequest $req): bool {
                $keys = $req->getBody()->getPublicKeys();

                $this->assertEquals('SSH_USER_ID', $req->getSshUserId());
                $this->assertNotNull($keys);
                $this->assertCount(2, $keys);
                $this->assertEquals('ssh-rsa FOOBAR', $keys[1]->getKey());
                return true;
            }))
            ->willReturn(new EmptyResponse(new Response()));

        SSHUserRecipe::assertSSHUser();
    }
}