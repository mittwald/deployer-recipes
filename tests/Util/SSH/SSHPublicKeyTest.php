<?php
declare(strict_types=1);

namespace Util\SSH;

use InvalidArgumentException;
use Mittwald\Deployer\Util\SSH\SSHPublicKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SSHPublicKey::class)]
class SSHPublicKeyTest extends TestCase
{
    public function testFromStringCorrectlyDeconstructsInputWithComment(): void
    {
        $publicKey = SSHPublicKey::fromString("ssh-rsa FOOBAR foo@bar");

        $this->assertEquals("ssh-rsa FOOBAR", $publicKey->publicKey);
        $this->assertEquals("foo@bar", $publicKey->comment);
    }

    public function testFromStringCorrectlyDeconstructsInputWithoutComment(): void
    {
        $publicKey = SSHPublicKey::fromString("ssh-rsa FOOBAR");

        $this->assertEquals("ssh-rsa FOOBAR", $publicKey->publicKey);
        $this->assertEquals("", $publicKey->comment);
    }

    public function testFromStringThrowsExceptionInInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SSHPublicKey::fromString("ssh-rsa");
    }
}