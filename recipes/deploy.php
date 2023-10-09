<?php

namespace Deployer;

use Deployer\Host\Host;
use Mittwald\Deployer\Recipes\AppRecipe;
use Mittwald\Deployer\Recipes\DomainRecipe;
use Mittwald\Deployer\Recipes\SSHUserRecipe;

AppRecipe::setup();
SSHUserRecipe::setup();
DomainRecipe::setup();

desc('Assert that the application is configured correctly on the mittwald platform');
task('mittwald:setup', [
    'mittwald:discover',
    'mittwald:sshuser',
    'mittwald:app',
    'mittwald:domain',
]);

before('deploy:setup', 'mittwald:setup');

/**
 * Shorthand function for defining a host with a preconfigured mittwald App.
 *
 * @param ?string $appId
 * @return Host
 */
function mittwald_app(string $appId = null): Host {
    return host('mittwald')
        ->set('mittwald_app_id', $appId ?? getenv("MITTWALD_APP_ID"));
}