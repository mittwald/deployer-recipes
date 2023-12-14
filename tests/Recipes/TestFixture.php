<?php

namespace Mittwald\Deployer\Recipes;

use Deployer\Component\ProcessRunner\ProcessRunner;
use Deployer\Deployer;
use Deployer\Host\Host;
use Deployer\Task\Context;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mittwald\Deployer\Client\MockClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use function Deployer\host;
use function Deployer\task;

class TestFixture
{
    public ProcessRunner&MockObject $processRunner;
    public MockClient $client;
    public Deployer $depl;
    public Filesystem $fs;

    public function __construct(TestCase $test)
    {
        $this->processRunner = $test->getMockBuilder(ProcessRunner::class)->disableOriginalConstructor()->getMock();
        $this->client = new MockClient($test);

        $this->fs = new Filesystem(new InMemoryFilesystemAdapter());

        // The constructor also resets the Deployer singleton, so we only need
        // to instantiate it once.
        $this->depl                = new Deployer(new Application());
        $this->depl->processRunner = $this->processRunner;
        $this->depl->output        = new NullOutput();

        Context::push(new Context(new Host('test')));

        task('deploy:symlink', function () {
        });

        host('test')
            ->set('mittwald_internal_hostname', 'test.internal');

        $this->depl->config->set('mittwald_client', $this->client);
        $this->depl->config->set('mittwald_filesystem', $this->fs);
        $this->depl->config->set('mittwald_token', 'TOKEN');
        $this->depl->config->set('mittwald_app_id', 'INSTALLATION_ID');
        $this->depl->config->set('ssh_copy_id', '~/.ssh/id_rsa.pub');
        $this->depl->config->set('selected_hosts', ['test']);
    }
}