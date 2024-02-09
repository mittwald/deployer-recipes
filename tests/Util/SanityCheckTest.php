<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SanityCheck::class)]
class SanityCheckTest extends TestCase
{
    public function testAppShortIDDoesNotTriggersException(): void
    {
        $this->expectNotToPerformAssertions();
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