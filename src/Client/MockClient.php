<?php

namespace Mittwald\Deployer\Client;

use Mittwald\ApiClient\Generated\V2\Client;
use Mittwald\ApiClient\Generated\V2\Clients\AIHosting\AIHostingClient;
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
use Mittwald\ApiClient\Generated\V2\Clients\LeadFyndr\LeadFyndrClient;
use Mittwald\ApiClient\Generated\V2\Clients\Mail\MailClient;
use Mittwald\ApiClient\Generated\V2\Clients\Marketplace\MarketplaceClient;
use Mittwald\ApiClient\Generated\V2\Clients\Misc\MiscClient;
use Mittwald\ApiClient\Generated\V2\Clients\Notification\NotificationClient;
use Mittwald\ApiClient\Generated\V2\Clients\PageInsights\PageInsightsClient;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ProjectClient;
use Mittwald\ApiClient\Generated\V2\Clients\ProjectFileSystem\ProjectFileSystemClient;
use Mittwald\ApiClient\Generated\V2\Clients\Relocation\RelocationClient;
use Mittwald\ApiClient\Generated\V2\Clients\SSHSFTPUser\SSHSFTPUserClient;
use Mittwald\ApiClient\Generated\V2\Clients\User\UserClient;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MockClient implements Client
{
    public ProjectClient|MockObject $project;
    public BackupClient|MockObject $backup;
    public SSHSFTPUserClient|MockObject $sshSFTPUser;
    public CronjobClient|MockObject $cronjob;
    public ContainerClient|MockObject $container;
    public AppClient|MockObject $app;
    public ProjectFileSystemClient|MockObject $projectFileSystem;
    public ContractClient|MockObject $contract;
    public DatabaseClient|MockObject $database;
    public DomainClient|MockObject $domain;
    public ConversationClient|MockObject $conversation;
    public CustomerClient|MockObject $customer;
    public UserClient|MockObject $user;
    public NotificationClient|MockObject $notification;
    public FileClient|MockObject $file;
    public MailClient|MockObject $mail;
    public ArticleClient|MockObject $article;
    public PageInsightsClient|MockObject $pageInsights;
    public RelocationClient|MockObject $relocation;
    public MarketplaceClient|MockObject $marketplace;
    public MiscClient|MockObject $misc;
    public LeadFyndrClient|MockObject $leadFyndr;
    public AIHostingClient|MockObject $aiHosting;

    public function __construct(TestCase $test)
    {
        $this->project = (new MockBuilder($test, ProjectClient::class))->getMock();
        $this->backup = (new MockBuilder($test, BackupClient::class))->getMock();
        $this->sshSFTPUser = (new MockBuilder($test, SSHSFTPUserClient::class))->getMock();
        $this->cronjob = (new MockBuilder($test, CronjobClient::class))->getMock();
        $this->container = (new MockBuilder($test, ContainerClient::class))->getMock();
        $this->app = (new MockBuilder($test, AppClient::class))->getMock();
        $this->projectFileSystem = (new MockBuilder($test, ProjectFileSystemClient::class))->getMock();
        $this->contract = (new MockBuilder($test, ContractClient::class))->getMock();
        $this->database = (new MockBuilder($test, DatabaseClient::class))->getMock();
        $this->domain = (new MockBuilder($test, DomainClient::class))->getMock();
        $this->conversation = (new MockBuilder($test, ConversationClient::class))->getMock();
        $this->customer = (new MockBuilder($test, CustomerClient::class))->getMock();
        $this->user = (new MockBuilder($test, UserClient::class))->getMock();
        $this->notification = (new MockBuilder($test, NotificationClient::class))->getMock();
        $this->file = (new MockBuilder($test, FileClient::class))->getMock();
        $this->mail = (new MockBuilder($test, MailClient::class))->getMock();
        $this->article = (new MockBuilder($test, ArticleClient::class))->getMock();
        $this->pageInsights = (new MockBuilder($test, PageInsightsClient::class))->getMock();
        $this->relocation = (new MockBuilder($test, RelocationClient::class))->getMock();
        $this->marketplace = (new MockBuilder($test, MarketplaceClient::class))->getMock();
        $this->misc = (new MockBuilder($test, MiscClient::class))->getMock();
        $this->leadFyndr = (new MockBuilder($test, LeadFyndrClient::class))->getMock();
        $this->aiHosting = (new MockBuilder($test, AIHostingClient::class))->getMock();
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

    public function container(): ContainerClient
    {
        return $this->container;
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

    public function misc(): MiscClient
    {
        return $this->misc;
    }

    public function leadFyndr(): LeadFyndrClient
    {
        return $this->leadFyndr;
    }

    public function aiHosting(): AIHostingClient
    {
        return $this->aiHosting;
    }

}