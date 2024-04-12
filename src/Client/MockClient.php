<?php

namespace Mittwald\Deployer\Client;

use Mittwald\ApiClient\Generated\V2\Client;
use Mittwald\ApiClient\Generated\V2\Clients\App\AppClient;
use Mittwald\ApiClient\Generated\V2\Clients\Article\ArticleClient;
use Mittwald\ApiClient\Generated\V2\Clients\Backup\BackupClient;
use Mittwald\ApiClient\Generated\V2\Clients\Container\ContainerClient;
use Mittwald\ApiClient\Generated\V2\Clients\Contract\ContractClient;
use Mittwald\ApiClient\Generated\V2\Clients\Conversation\ConversationClient;
use Mittwald\ApiClient\Generated\V2\Clients\Cronjob\CronjobClient;
use Mittwald\ApiClient\Generated\V2\Clients\Customer\CustomerClient;
use Mittwald\ApiClient\Generated\V2\Clients\Database\DatabaseClient;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\DomainClient;
use Mittwald\ApiClient\Generated\V2\Clients\File\FileClient;
use Mittwald\ApiClient\Generated\V2\Clients\Mail\MailClient;
use Mittwald\ApiClient\Generated\V2\Clients\Marketplace\MarketplaceClient;
use Mittwald\ApiClient\Generated\V2\Clients\Notification\NotificationClient;
use Mittwald\ApiClient\Generated\V2\Clients\PageInsights\PageInsightsClient;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ProjectClient;
use Mittwald\ApiClient\Generated\V2\Clients\ProjectFileSystem\ProjectFileSystemClient;
use Mittwald\ApiClient\Generated\V2\Clients\Relocation\RelocationClient;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\SSHSFTPUserClient;
use Mittwald\ApiClient\Generated\V2\Clients\User\UserClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockClient implements Client
{
    public ProjectClient&MockObject $project;
    public BackupClient&MockObject $backup;
    public SSHSFTPUserClient&MockObject $sshSFTPUser;
    public CronjobClient&MockObject $cronjob;
    public AppClient&MockObject $app;
    public ProjectFileSystemClient&MockObject $projectFileSystem;
    public ContractClient&MockObject $contract;
    public DatabaseClient&MockObject $database;
    public DomainClient&MockObject $domain;
    public ConversationClient&MockObject $conversation;
    public CustomerClient&MockObject $customer;
    public UserClient&MockObject $user;
    public NotificationClient&MockObject $notification;
    public FileClient&MockObject $file;
    public MailClient&MockObject $mail;
    public ArticleClient&MockObject $article;
    public ContainerClient&MockObject $container;
    public PageInsightsClient&MockObject $pageInsights;
    public RelocationClient&MockObject $relocation;
    public MarketplaceClient&MockObject $marketplace;

    public function __construct(TestCase $test)
    {
        $this->project = $test->getMockBuilder(ProjectClient::class)->getMock();
        $this->backup = $test->getMockBuilder(BackupClient::class)->getMock();
        $this->sshSFTPUser = $test->getMockBuilder(SSHSFTPUserClient::class)->getMock();
        $this->cronjob = $test->getMockBuilder(CronjobClient::class)->getMock();
        $this->app = $test->getMockBuilder(AppClient::class)->getMock();
        $this->projectFileSystem = $test->getMockBuilder(ProjectFileSystemClient::class)->getMock();
        $this->contract = $test->getMockBuilder(ContractClient::class)->getMock();
        $this->database = $test->getMockBuilder(DatabaseClient::class)->getMock();
        $this->domain = $test->getMockBuilder(DomainClient::class)->getMock();
        $this->conversation = $test->getMockBuilder(ConversationClient::class)->getMock();
        $this->customer = $test->getMockBuilder(CustomerClient::class)->getMock();
        $this->user = $test->getMockBuilder(UserClient::class)->getMock();
        $this->notification = $test->getMockBuilder(NotificationClient::class)->getMock();
        $this->file = $test->getMockBuilder(FileClient::class)->getMock();
        $this->mail = $test->getMockBuilder(MailClient::class)->getMock();
        $this->article = $test->getMockBuilder(ArticleClient::class)->getMock();
        $this->container = $test->getMockBuilder(ContainerClient::class)->getMock();
        $this->pageInsights = $test->getMockBuilder(PageInsightsClient::class)->getMock();
        $this->relocation = $test->getMockBuilder(RelocationClient::class)->getMock();
        $this->marketplace = $test->getMockBuilder(MarketplaceClient::class)->getMock();
    }

    public function project(): ProjectClient
    {
        return $this->project;
    }

    public function backup(): BackupClient
    {
        return $this->backup;
    }

    public function sshSFTPUser(): SSHSFTPUserClient
    {
        return $this->sshSFTPUser;
    }

    public function cronjob(): CronjobClient
    {
        return $this->cronjob;
    }

    public function app(): AppClient
    {
        return $this->app;
    }

    public function projectFileSystem(): ProjectFileSystemClient
    {
        return $this->projectFileSystem;
    }

    public function contract(): ContractClient
    {
        return $this->contract;
    }

    public function database(): DatabaseClient
    {
        return $this->database;
    }

    public function domain(): DomainClient
    {
        return $this->domain;
    }

    public function conversation(): ConversationClient
    {
        return $this->conversation;
    }

    public function customer(): CustomerClient
    {
        return $this->customer;
    }

    public function user(): UserClient
    {
        return $this->user;
    }

    public function notification(): NotificationClient
    {
        return $this->notification;
    }

    public function file(): FileClient
    {
        return $this->file;
    }

    public function mail(): MailClient
    {
        return $this->mail;
    }

    public function article(): ArticleClient
    {
        return $this->article;
    }

    public function container(): ContainerClient
    {
        return $this->container;
    }

    public function pageInsights(): PageInsightsClient
    {
        return $this->pageInsights;
    }

    public function relocation(): RelocationClient
    {
        return $this->relocation;
    }

    public function marketplace(): MarketplaceClient
    {
        return $this->marketplace;
    }

}