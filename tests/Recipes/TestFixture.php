<?php

namespace Mittwald\Deployer\Recipes;

use Composer\Semver\Semver;
use Deployer\Component\ProcessRunner\ProcessRunner;
use Deployer\Deployer;
use Deployer\Host\Host;
use Deployer\Support\ObjectProxy;
use Deployer\Task\Context;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwares\ListSystemsoftwaresOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwareversions\ListSystemsoftwareversionsOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwareversions\ListSystemsoftwareversionsRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectOKResponse;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\App\AppInstallation;
use Mittwald\ApiClient\Generated\V2\Schemas\App\SystemSoftware;
use Mittwald\ApiClient\Generated\V2\Schemas\App\SystemSoftwareVersion;
use Mittwald\ApiClient\Generated\V2\Schemas\App\VersionStatus;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\DeprecatedProjectReadinessStatus;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\ProjectStatus;
use Mittwald\Deployer\Client\MockClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use function Deployer\host;
use function Deployer\task;
use function PHPUnit\Framework\any;

class TestFixture
{
    public ProcessRunner&MockObject $processRunner;
    public MockClient $client;
    public Deployer $depl;
    public Filesystem $fs;
    public Host|ObjectProxy $host;

    public AppInstallation $appInstallation;
    public Project $project;

    public function __construct(TestCase $test)
    {
        $this->processRunner = $test->getMockBuilder(ProcessRunner::class)->disableOriginalConstructor()->getMock();
        $test->registerMockObject($this->processRunner);

        $this->client        = new MockClient($test);

        $this->fs = new Filesystem(new InMemoryFilesystemAdapter());

        // The constructor also resets the Deployer singleton, so we only need
        // to instantiate it once.
        $this->depl                = new Deployer(new Application());
        $this->depl->processRunner = $this->processRunner;
        $this->depl->output        = new NullOutput();

        $this->host = host('test')
            ->set('mittwald_internal_hostname', 'test.internal');

        Context::push(new Context($this->host));

        task('deploy:symlink', function () {
        });

        $this->depl->config->set('mittwald_client', $this->client);
        $this->depl->config->set('mittwald_filesystem', $this->fs);
        $this->depl->config->set('mittwald_token', 'TOKEN');
        $this->depl->config->set('mittwald_app_id', 'INSTALLATION_ID');
        $this->depl->config->set('ssh_copy_id', '~/.ssh/id_rsa.pub');
        $this->depl->config->set('selected_hosts', ['test']);
        $this->depl->config->set('current_path', 'current');

        $this->appInstallation = (new AppInstallation(
            'APP_ID',
            new VersionStatus('1.0.0'),
            'description',
            false,
            'INSTALLATION_ID',
            '/foo',
            'a-XXXXXX',
        ))
            ->withProjectId('PROJECT_ID');

        $this->project = (new Project(
            createdAt: new \DateTime(),
            customerId: 'CUSTOMER_ID',
            description: 'Description',
            directories: ['Web' => '/html'],
            enabled: true,
            id: 'PROJECT_ID',
            isReady: true,
            readiness: DeprecatedProjectReadinessStatus::ready,
            shortId: 'p-XXXXXX',
            status: ProjectStatus::ready,
            statusSetAt: new \DateTime(),
        ))
            ->withClusterDomain('project.host')
            ->withClusterID('testing');

    }

    public function setupDefaultAppInstallation(): void
    {
        $this->client->app->expects(any())
            ->method('getAppinstallation')
            ->willReturnCallback(function (GetAppinstallationRequest $req): GetAppinstallationOKResponse {
                return new GetAppinstallationOKResponse(
                    $this->appInstallation
                        ->withId($req->getAppinstallationId()),
                );
            });
        $this->client->project->expects(any())
            ->method('getProject')
            ->willReturnCallback(fn(GetProjectRequest $req): GetProjectOKResponse => new GetProjectOKResponse(
                $this->project->withId($req->getProjectId()),
            ));

        $this->client->app->expects(any())
            ->method('listSystemsoftwares')
            ->willReturn(
                new ListSystemsoftwaresOKResponse([
                    new SystemSoftware('SYSTEMSOFTWARE_PHP_ID', 'php', []),
                    new SystemSoftware('SYSTEMSOFTWARE_COMPOSER_ID', 'composer', []),
                ])
            );

        $phpVersions = [
            new SystemSoftwareVersion(
                '7.4.0',
                'PHP_7_4_ID',
                '7.4.0',
            ),
            new SystemSoftwareVersion(
                '8.2.0',
                'PHP_8_2_ID',
                '8.2.0',
            ),
        ];

        $this->client->app->expects(any())
            ->method('listSystemsoftwareversions')
            ->willReturnCallback(function (ListSystemsoftwareversionsRequest $req) use ($phpVersions) {
                $responses = array_filter($phpVersions, fn(SystemSoftwareVersion $version) => Semver::satisfies($version->getInternalVersion(), $req->getVersionRange() ?? "*"));
                return new ListSystemsoftwareversionsOKResponse($responses);
            });
    }
}