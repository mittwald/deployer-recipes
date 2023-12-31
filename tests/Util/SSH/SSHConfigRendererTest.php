<?php

namespace Mittwald\Deployer\Util\SSH;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SSHConfigRenderer::class)]
#[CoversClass(SSHHost::class)]
#[CoversClass(SSHConfig::class)]
class SSHConfigRendererTest extends TestCase
{
    public function testRendersConfigCorrectlyWithSingleHost(): void
    {
        $config = (new SSHConfig("./.mw-deployer/sshconfig"))
            ->withHost((new SSHHost('test', 'test.example.com'))->withIdentityFile('~/.ssh/id_rsa'));
        $rendered = (new SSHConfigRenderer($config))->render();

        $expected = <<<SSHCONFIG
Host test
    HostName test.example.com
    StrictHostKeyChecking accept-new
    IdentityFile ~/.ssh/id_rsa
SSHCONFIG;

        $this->assertEquals($expected, trim($rendered));
    }
    public function testRendersConfigCorrectlyWithMultipleHosts(): void
    {
        $config = (new SSHConfig("./.mw-deployer/sshconfig"))
            ->withHost((new SSHHost('test', 'test.example.com'))->withIdentityFile('~/.ssh/id_rsa'))
            ->withHost((new SSHHost('test2', 'test2.example.com'))->withIdentityFile('~/.ssh/id_rsa'));
        $rendered = (new SSHConfigRenderer($config))->render();

        $expected = <<<SSHCONFIG
Host test
    HostName test.example.com
    StrictHostKeyChecking accept-new
    IdentityFile ~/.ssh/id_rsa

Host test2
    HostName test2.example.com
    StrictHostKeyChecking accept-new
    IdentityFile ~/.ssh/id_rsa
SSHCONFIG;

        $this->assertEquals($expected, trim($rendered));
    }
}