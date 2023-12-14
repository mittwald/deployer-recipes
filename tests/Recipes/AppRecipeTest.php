<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Recipes;

use GuzzleHttp\Psr7\Response;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use function Deployer\set;
use function PHPUnit\Framework\arrayHasKey;
use function PHPUnit\Framework\assertThat;
use function PHPUnit\Framework\equalTo;
use function PHPUnit\Framework\isNull;
use function PHPUnit\Framework\never;
use function PHPUnit\Framework\once;

#[CoversClass(AppRecipe::class)]
class AppRecipeTest extends TestCase
{
    private TestFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new TestFixture($this);
        $this->fixture->setupDefaultAppInstallation();

        AppRecipe::setup();
    }

    public function testDiscoverSetsDeployPath(): void
    {
        AppRecipe::discover();

        assertThat($this->fixture->host->get('deploy_path'), equalTo('/html/foo'));
    }

    public function testDiscoverSetsHTTPUser(): void
    {
        AppRecipe::discover();

        assertThat($this->fixture->host->get('http_user'), equalTo('p-XXXXXX'));
    }

    public function testDiscoverSetsWriteableMode(): void
    {
        AppRecipe::discover();

        assertThat($this->fixture->host->get('writable_mode'), equalTo('chmod'));
    }

    public function testDiscoverSetsInternalHostname(): void
    {
        AppRecipe::discover();

        assertThat($this->fixture->host->get('mittwald_internal_hostname'), equalTo('ssh.testing.project.host'));
    }

    public function testDiscoverSetsPHP(): void
    {
        AppRecipe::discover();

        assertThat($this->fixture->host->get('bin/php'), equalTo('/usr/bin/php'));
    }

    #[Depends('testDiscoverSetsDeployPath')]
    public function testAssertDocumentRootDoesNotModifyInstallationWhenDocumentRootIsUpToDate(): void
    {
        $this->fixture->appInstallation = $this->fixture->appInstallation->withCustomDocumentRoot('/current');

        $this->fixture->client->app->expects(never())
            ->method('patchAppinstallation');

        AppRecipe::discover();
        AppRecipe::assertDocumentRoot();
    }

    #[Depends('testDiscoverSetsDeployPath')]
    public function testAssertDocumentRootSetsDocumentRootWhenNotSet(): void
    {
        $this->fixture->appInstallation = $this->fixture->appInstallation->withoutCustomDocumentRoot();

        $this->fixture->client->app->expects(once())
            ->method('patchAppinstallation')
            ->with(new Callback(function (PatchAppinstallationRequest $req): bool {
                assertThat($req->getAppInstallationId(), equalTo($this->fixture->appInstallation->getId()));
                assertThat($req->getBody()->getCustomDocumentRoot(), equalTo('/current'));
                assertThat($req->getBody()->getSystemSoftware(), isNull());
                return true;
            }))
            ->willReturn(new EmptyResponse(new Response()));

        AppRecipe::discover();
        AppRecipe::assertDocumentRoot();
    }

    #[Depends('testDiscoverSetsDeployPath')]
    public function testAssertDocumentRootSetsDocumentRootWhenSetDifferently(): void
    {
        $this->fixture->appInstallation = $this->fixture->appInstallation->withCustomDocumentRoot('/foo');

        $this->fixture->client->app->expects(once())
            ->method('patchAppinstallation')
            ->with(new Callback(function (PatchAppinstallationRequest $req): bool {
                assertThat($req->getAppInstallationId(), equalTo($this->fixture->appInstallation->getId()));
                assertThat($req->getBody()->getCustomDocumentRoot(), equalTo('/current'));
                assertThat($req->getBody()->getSystemSoftware(), isNull());
                return true;
            }))
            ->willReturn(new EmptyResponse(new Response()));

        AppRecipe::discover();
        AppRecipe::assertDocumentRoot();
    }

    #[Depends('testDiscoverSetsDeployPath')]
    public function testAssertDocumentRootSetsDocumentRootWhenPublicPathIsSet(): void
    {
        set('public_path', 'public');

        $this->fixture->appInstallation = $this->fixture->appInstallation->withoutCustomDocumentRoot();

        $this->fixture->client->app->expects(once())
            ->method('patchAppinstallation')
            ->with(new Callback(function (PatchAppinstallationRequest $req): bool {
                assertThat($req->getAppInstallationId(), equalTo($this->fixture->appInstallation->getId()));
                assertThat($req->getBody()->getCustomDocumentRoot(), equalTo('/current/public'));
                assertThat($req->getBody()->getSystemSoftware(), isNull());
                return true;
            }))
            ->willReturn(new EmptyResponse(new Response()));

        AppRecipe::discover();
        AppRecipe::assertDocumentRoot();
    }

    public function testAssertDependenciesPatchesDependencies(): void
    {
        set('mittwald_app_dependencies', ['php' => '~8.2']);

        $this->fixture->client->app->expects(once())
            ->method('patchAppinstallation')
            ->with(new Callback(function (PatchAppinstallationRequest $req): bool {
                assertThat($req->getAppInstallationId(), equalTo($this->fixture->appInstallation->getId()));
                assertThat($req->getBody()->getCustomDocumentRoot(), isNull());
                assertThat($req->getBody()->getSystemSoftware(), arrayHasKey('SYSTEMSOFTWARE_PHP_ID'));
                assertThat($req->getBody()->getSystemSoftware()['SYSTEMSOFTWARE_PHP_ID']->getSystemSoftwareVersion(), equalTo('PHP_8_2_ID'));
                return true;
            }))
            ->willReturn(new EmptyResponse(new Response()));

        AppRecipe::assertDependencies();
    }
}