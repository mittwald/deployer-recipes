<?php

namespace Deployer;

require 'recipe/common.php';
require 'contrib/rsync.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/mittwald/deployer-recipes/recipes/deploy.php';

// Config

set('rsync_src', __DIR__);
set('php_version', '8.2');
set('domain', getenv("MITTWALD_APP_DOMAIN"));

// Hosts

mittwald_app(getenv("MITTWALD_APP_ID"))
    ->set('public_path', '/');

// Hooks

task('deploy:prepare', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'rsync',
    'deploy:shared',
    'deploy:writable',
]);

after('deploy:failed', 'deploy:unlock');