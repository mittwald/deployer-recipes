<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Recipes;

use GuzzleHttp\Psr7\Response;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ListProjects\ListProjectsOKResponse;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\DeprecatedProjectReadinessStatus;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\ProjectListItem;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\ProjectListItemCustomerMeta;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\ProjectStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use function Deployer\get;
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

    public function testProjectUUIDIsReturnedAsIsWhenProjectIDIsNotAShortID(): void
    {
        set('mittwald_project_id', 'PROJECT_ID');

        $this->fixture->client->project->expects(never())
            ->method('listProjects');

        assertThat(get('mittwald_project_uuid'), equalTo('PROJECT_ID'));
    }

    public function testProjectUUIDIsResolvedFromShortID(): void
    {
        set('mittwald_project_id', 'p-XXXXXX');

        $this->fixture->client->project->expects(once())
            ->method('listProjects')
            ->willReturn(new ListProjectsOKResponse([
                $this->buildProjectListItem('OTHER_PROJECT_ID', 'p-YYYYYY'),
                $this->buildProjectListItem('PROJECT_ID', 'p-XXXXXX'),
            ]));

        assertThat(get('mittwald_project_uuid'), equalTo('PROJECT_ID'));
    }

    public function testProjectUUIDResolutionFailsWhenShortIDIsUnknown(): void
    {
        set('mittwald_project_id', 'p-ZZZZZZ');

        $this->fixture->client->project->expects(once())
            ->method('listProjects')
            ->willReturn(new ListProjectsOKResponse([
                $this->buildProjectListItem('PROJECT_ID', 'p-XXXXXX'),
            ]));

        $this->expectExceptionMessage('Could not find project with id p-ZZZZZZ');

        get('mittwald_project_uuid');
    }

    private function buildProjectListItem(string $id, string $shortId): ProjectListItem
    {
        return new ProjectListItem(
            backupStorageUsageInBytes: 0,
            backupStorageUsageInBytesSetAt: new \DateTime(),
            createdAt: new \DateTime(),
            customerId: 'CUSTOMER_ID',
            customerMeta: new ProjectListItemCustomerMeta('CUSTOMER_ID'),
            deletionRequested: false,
            description: 'Description',
            enabled: true,
            id: $id,
            isReady: true,
            readiness: DeprecatedProjectReadinessStatus::ready,
            serverGroupId: 'SERVER_GROUP_ID',
            shortId: $shortId,
            status: ProjectStatus::ready,
            statusSetAt: new \DateTime(),
            supportedFeatures: [],
            webStorageUsageInBytes: 0,
            webStorageUsageInBytesSetAt: new \DateTime(),
        );
    }
}
