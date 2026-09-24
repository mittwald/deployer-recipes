<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Deployer 7 does not declare an autoloader for its function files; Deployer 8
// does. Only require them manually when they have not been loaded yet.
if (!function_exists('Deployer\host')) {
    require_once __DIR__ . '/../vendor/deployer/deployer/src/functions.php';
    require_once __DIR__ . '/../vendor/deployer/deployer/src/Support/helpers.php';
}
