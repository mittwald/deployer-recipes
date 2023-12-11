<?php
declare(strict_types=1);

namespace Util;

use InvalidArgumentException;
use Mittwald\Deployer\Util\SanityCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SanityCheck::class)]
class SanityCheckTest extends TestCase
{
    public function testAppShortIDTriggersException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SanityCheck::assertAppInstallationID("a-123456");
    }

    public function testProjectShortIDTriggersException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SanityCheck::assertAppInstallationID("p-123456");
    }

    public function testUUIDDoesNotTriggerException(): void
    {
        $this->expectNotToPerformAssertions();
        SanityCheck::assertAppInstallationID("12345678-1234-1234-1234-123456789012");
    }
}