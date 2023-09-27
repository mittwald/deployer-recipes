<?php

namespace Deployer;

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