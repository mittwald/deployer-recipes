<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util;

/**
 * Helper class to check for common misconfigurations.
 */
class SanityCheck
{
    /**
     * Check the provided app installation ID for common misconfigurations.
     */
    public static function assertAppInstallationID(string $id): void
    {
        if (preg_match('/^p-[a-z0-9]+$/', $id)) {
            throw new \InvalidArgumentException('The provided app installation ID looks like a _project_ short ID. Please provide an app ID, and make sure to use the full ID (which should be a UUIDv4).');
        }
    }
}