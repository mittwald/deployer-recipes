<?php

namespace Deployer;

use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequestBody;
use Mittwald\Deployer\Recipes\AppRecipe;
use Mittwald\Deployer\Recipes\BaseRecipe;
use Mittwald\Deployer\Recipes\SSHUserRecipe;
use Mittwald\Deployer\Client\AppClient;

require_once __DIR__ . '/../vendor/autoload.php';

AppRecipe::set();
SSHUserRecipe::set();

desc('Look up the app installation for the current host');
task('mittwald:discover', function () {
    if (!get('mittwald_autoprovision')) {
        return;
    }

    if ($app = AppRecipe::getAppInstallation()) {
        $project    = AppRecipe::getProject();
        $deployPath = $project->getDirectories()["Web"] . $app->getInstallationPath();

        currentHost()->set('deploy_path', $deployPath);
        info("setting deployment path to <fg=magenta;options=bold>{{deploy_path}}</>");
    } else if ($deployPath = get('deploy_path')) {
        writeln("searching for app deployed at {$deployPath}");

        $app = AppRecipe::getAppInstallation();

        writeln("app uuid: {$app->getId()}");
    }

    $project        = BaseRecipe::getProject();
    $projectSSHHost = "ssh.{$project->getClusterID()}.{$project->getClusterDomain()}";

    currentHost()->set('mittwald_internal_hostname', $projectSSHHost);

    info("setting hostname to <fg=magenta;options=bold>{{hostname}}</>");

    // Override the default setting, which might try to respect the {{php_version}} setting.
    currentHost()->set('bin/php', '/usr/bin/php');
});

desc('Asserts that the document root of an app is configured correctly');
task('mittwald:app:docroot', function () {
    $app    = AppRecipe::getAppInstallation();
    $client = BaseRecipe::getClient()->app();

    $relativeCurrentPath = str_replace(get('deploy_path'), '', get('current_path'));
    $relativeCurrentPath = trim($relativeCurrentPath, '/');

    $desiredDocumentRoot = "/{$relativeCurrentPath}";
    if ($publicPath = get("public_path")) {
        $desiredDocumentRoot .= '/' . ltrim($publicPath, '/');
    }

    if ($app->getCustomDocumentRoot() === $desiredDocumentRoot) {
        info("document root already set to <fg=magenta;options=bold>{$desiredDocumentRoot}</>");
        return;
    }

    info("setting document root to <fg=magenta;options=bold>{$desiredDocumentRoot}</>");

    $appPatchRequest = new PatchAppinstallationRequest(
        $app->getId(),
        (new PatchAppinstallationRequestBody())
            ->withCustomDocumentRoot($desiredDocumentRoot),
    );

    $appPatchResponse = $client->patchAppinstallation($appPatchRequest);
    if (!$appPatchResponse instanceof EmptyResponse) {
        throw new \Exception('Could not patch app');
    }
});

task('mittwald:sshconfig', function () {
    $config = "";

    foreach (selectedHosts() as $host) {
        if ($internal = $host->get('mittwald_internal_hostname')) {
            $name   = $host->getAlias() ?? $host->getHostname();
            $config .= "Host {$name}\n\tHostName {$internal}\n\n";
        }
    }

    if (!is_dir('./.mw-deployer')) {
        mkdir('./.mw-deployer', permissions: 0755, recursive: true);
    }

    file_put_contents('./.mw-deployer/sshconfig', $config);

    foreach (selectedHosts() as $host) {
        if ($host->has('mittwald_internal_hostname')) {
            $host->set('config_file', './.mw-deployer/sshconfig');
        }
    }
})->once();

desc('Asserts that the SSH user for the mittwald platform is configured correctly');
task('mittwald:sshuser', function () {
    $sshUser = SSHUserRecipe::assertSSHUser();
    $app     = AppRecipe::getAppInstallation();

    $remoteUser = "{$sshUser->getUserName()}@app-{$app->getId()}";

    info("setting SSH user to <fg=magenta;options=bold>{$remoteUser}</>");

    currentHost()->set('remote_user', $remoteUser);
});

task('mittwald:app:dependencies', function () {
    $dependencies = get('mittwald_app_dependencies', []);
    $client = new AppClient(BaseRecipe::getClient()->app());

    foreach ($dependencies as $key => $value) {
        $dependencies[$key] = parse($value);
        info("setting dependency {$key} to <fg=magenta;options=bold>{$dependencies[$key]}</>");
    }

    $client->setSystemSoftwareVersions(
        AppRecipe::getAppInstallation()->getId(),
        $dependencies
    );
});

after('mittwald:sshuser', 'mittwald:sshconfig');

task('mittwald:app', [
    'mittwald:app:docroot',
    'mittwald:app:dependencies',
]);

desc('Assert that the application is configured correctly on the mittwald platform');
task('mittwald:setup', [
    'mittwald:discover',
    'mittwald:sshuser',
    'mittwald:app',
]);

before('deploy:setup', 'mittwald:setup');