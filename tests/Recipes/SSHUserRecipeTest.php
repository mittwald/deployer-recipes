<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Recipes;

use GuzzleHttp\Psr7\Response;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserCreatedResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\CreateSshUser\CreateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\ListSshUsers\ListSshUsersOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\UpdateSshUser\UpdateSshUserRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\AuthenticationAlternative2;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\PublicKey;
use Mittwald\ApiClient\Generated\V2\Schemas\Sshuser\SshUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use function Deployer\set;
use function PHPUnit\Framework\assertThat;
use function PHPUnit\Framework\equalTo;
use function PHPUnit\Framework\isTrue;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

#[CoversClass(SSHUserRecipe::class)]
class SSHUserRecipeTest extends TestCase
{
    private TestFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestFixture($this);
        $this->fixture->setupDefaultAppInstallation();
        $this->fixture->processRunner->expects($this->any())
            ->method('run')
            ->with($this->anything(), 'cat ~/.ssh/id_rsa.pub', $this->anything())
            ->willReturn('ssh-rsa FOOBAR test@local');

        AppRecipe::setup();
        SSHUserRecipe::setup();
    }

    public function testAssertSSHUserCreatesSSHUserWhenItDoesNotExist(): void
    {
        $this->fixture->client->sshSFTPUser->expects(once())
            ->method('listSshUsers')
            ->willReturn(new ListSshUsersOKResponse([]));
        $this->fixture->client->sshSFTPUser->expects(once())
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

    public function testAssertSSHUserCreatesSSHUserWithPublicKeyFromDefinedFile(): void
    {
        set('mittwald_ssh_public_key_file', '/foo/id_rsa.pub');
        $this->fixture->fs->write('/foo/id_rsa.pub', 'ssh-rsa BARBAZ test@local');

        $this->fixture->client->sshSFTPUser->expects(once())
            ->method('listSshUsers')
            ->willReturn(new ListSshUsersOKResponse([]));
        $this->fixture->client->sshSFTPUser->expects(once())
            ->method('createSshUser')
            ->with(new Callback(function (CreateSshUserRequest $request): bool {
                $sshUser = $request->getBody();
                $auth    = $sshUser->getAuthentication();

                $this->assertEquals('deployer', $sshUser->getDescription());
                $this->assertInstanceOf(AuthenticationAlternative2::class, $auth);

                $publicKeys = $auth->getPublicKeys();

                $this->assertCount(1, $publicKeys);
                $this->assertEquals('ssh-rsa BARBAZ', $publicKeys[0]->getKey());
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
        $this->fixture->client->sshSFTPUser->expects(once())
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
        $this->fixture->client->sshSFTPUser->expects(never())
            ->method('createSshUser');
        $this->fixture->client->sshSFTPUser->expects(never())
            ->method('updateSshUser');

        SSHUserRecipe::assertSSHUser();
    }

    public function testAssertSSHUserUpdatesExistingPublicKeys(): void
    {
        $this->fixture->client->sshSFTPUser->expects(once())
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
        $this->fixture->client->sshSFTPUser->expects(never())
            ->method('createSshUser');
        $this->fixture->client->sshSFTPUser->expects(once())
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

    public function testAssertSSHConfigWritesSSHConfigWithDefaultPrivateKey(): void
    {
        SSHUserRecipe::assertSSHConfig();

        assertThat($this->fixture->fs->has('.mw-deployer/sshconfig'), isTrue());
        assertThat($this->fixture->fs->read('.mw-deployer/sshconfig'), equalTo('Host test
    HostName test.internal
    StrictHostKeyChecking accept-new
    IdentityFile ~/.ssh/id_rsa

'));
    }

    public function testAssertSSHConfigWritesSSHConfigWithPrivateKeyFile(): void
    {
        set('mittwald_ssh_private_key_file', '/foo/id_rsa');

        SSHUserRecipe::assertSSHConfig();

        assertThat($this->fixture->fs->has('.mw-deployer/sshconfig'), isTrue());
        assertThat($this->fixture->fs->read('.mw-deployer/sshconfig'), equalTo('Host test
    HostName test.internal
    StrictHostKeyChecking accept-new
    IdentityFile /foo/id_rsa

'));
    }

    public function testAssertSSHConfigWritesSSHConfigWithPrivateKeyContents(): void
    {
        set('mittwald_ssh_private_key', 'PRIVATE KEY CONTENTS');

        SSHUserRecipe::assertSSHConfig();

        assertThat($this->fixture->fs->has('.mw-deployer/sshconfig'), isTrue());
        assertThat($this->fixture->fs->read('.mw-deployer/sshconfig'), equalTo('Host test
    HostName test.internal
    StrictHostKeyChecking accept-new
    IdentityFile ./.mw-deployer/id_rsa

'));

        assertThat($this->fixture->fs->has('.mw-deployer/id_rsa'), isTrue());
        assertThat($this->fixture->fs->read('.mw-deployer/id_rsa'), equalTo('PRIVATE KEY CONTENTS'));
    }
}