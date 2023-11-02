<?php

namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallation200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListAppinstallations\ListAppinstallations200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListAppinstallations\ListAppinstallationsRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProject200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ListProjects\ListProjects200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ListProjects\ListProjectsRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\App\AppInstallation;
use Mittwald\Deployer\Client\AppClient;
use function Deployer\{currentHost, get, info, parse, set, Support\starts_with, task};
use function Mittwald\Deployer\get_array;
use function Mittwald\Deployer\get_str;
use function Mittwald\Deployer\get_str_nullable;

class AppRecipe
{
    public static function setup(): void
    {
        set('mittwald_token', function (): string {
            $token = getenv('MITTWALD_API_TOKEN');
            if (!$token) {
                throw new \Exception('MITTWALD_API_TOKEN not set');
            }

            return $token;
        });

        set('mittwald_project_id', function (): string {
            if ($projectId = static::getAppInstallation()->getProjectId()) {
                set('mittwald_project_id', $projectId);
                return $projectId;
            }

            throw new \Exception('please set mittwald_project_id when mittwald_app_id is _not_ set');
        });

        set('mittwald_project_uuid', function (): string {
            $client    = BaseRecipe::getClient()->project();
            $projectId = get_str('mittwald_project_id');

            if (!starts_with($projectId, "p-")) {
                return $projectId;
            }

            $projectResponse = $client->listProjects(new ListProjectsRequest());
            if (!$projectResponse instanceof ListProjects200Response) {
                throw new \Exception('Could not list projects');
            }

            foreach ($projectResponse->getBody() as $project) {
                if ($project->getShortId() === get('mittwald_project_id') || $project->getId() === get('mittwald_project_id')) {
                    return $project->getId();
                }
            }

            throw new \Exception("Could not find project with id {$projectId}");
        });

        set('mittwald_project', function (): array {
            $client    = BaseRecipe::getClient();
            $projectId = get_str('mittwald_project_uuid');

            $projectRequest  = new GetProjectRequest($projectId);
            $projectResponse = $client->project()->getProject($projectRequest);
            if (!$projectResponse instanceof GetProject200Response) {
                throw new \Exception('could not get projects');
            }

            return $projectResponse->getBody()->toJson();
        });

        set('mittwald_app', function (): array {
            $client = BaseRecipe::getClient()->app();

            if ($appID = get_str_nullable('mittwald_app_id')) {
                $appResponse = $client->getAppinstallation(new GetAppinstallationRequest($appID));
                if (!$appResponse instanceof GetAppinstallation200Response) {
                    throw new \Exception('could not get app');
                }

                return $appResponse->getBody()->toJson();
            }

            if ($deployPath = get_str_nullable('deploy_path')) {
                $project = BaseRecipe::getProject();

                $appsResponse = $client->listAppinstallations(new ListAppinstallationsRequest($project->getId()));
                if (!$appsResponse instanceof ListAppinstallations200Response) {
                    throw new \Exception('could not list apps');
                }

                $webBasePath = $project->getDirectories()["Web"];

                foreach ($appsResponse->getBody() as $app) {
                    if ($webBasePath . $app->getInstallationPath() === $deployPath) {
                        return $app->toJson();
                    }
                }

                throw new \Exception("could not find app with installation path {$deployPath}");
            }

            throw new \Exception("neither mittwald_app_id nor deploy_path set");
        });

        set('mittwald_app_dependencies', [
            'php' => '{{php_version}}',
        ]);

        task('mittwald:discover', function (): void {
            static::discover();
        })
            ->desc('Look up the app installation for the current host');

        task('mittwald:app:docroot', function (): void {
            static::assertDocumentRoot();
        })
            ->desc('Asserts that the document root of an app is configured correctly');

        task('mittwald:app:dependencies', function (): void {
            static::assertDependencies();
        })
            ->desc('Make sure that the requested dependencies are installed');

        task('mittwald:app', [
            'mittwald:app:docroot',
            'mittwald:app:dependencies',
        ]);
    }

    public static function getAppInstallation(): AppInstallation
    {
        return AppInstallation::buildFromInput(get_array('mittwald_app'), validate: false);
    }

    public static function discover(): void
    {
        $app        = AppRecipe::getAppInstallation();
        $project    = BaseRecipe::getProject();
        $deployPath = $project->getDirectories()["Web"] . $app->getInstallationPath();

        currentHost()->set('deploy_path', $deployPath);
        info("setting deployment path to <fg=magenta;options=bold>{{deploy_path}}</>");

        $project        = BaseRecipe::getProject();
        $projectSSHHost = "ssh.{$project->getClusterID()}.{$project->getClusterDomain()}";

        currentHost()->set('mittwald_internal_hostname', $projectSSHHost);

        info("setting hostname to <fg=magenta;options=bold>{{hostname}}</>");

        // Override the default setting, which might try to respect the {{php_version}} setting.
        currentHost()->set('bin/php', '/usr/bin/php');
    }

    public static function assertDocumentRoot(): void
    {
        $app    = AppRecipe::getAppInstallation();
        $client = BaseRecipe::getClient()->app();

        /** @var string $relativeCurrentPath */
        $relativeCurrentPath = str_replace(get_str('deploy_path'), '', get_str('current_path'));
        $relativeCurrentPath = trim($relativeCurrentPath, '/');

        $desiredDocumentRoot = "/{$relativeCurrentPath}";
        if ($publicPath = get_str_nullable("public_path")) {
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
    }

    public static function assertDependencies(): void
    {
        /** @var array<non-empty-string, string> $dependencies */
        $dependencies = get_array('mittwald_app_dependencies', []);
        $client       = new AppClient(BaseRecipe::getClient()->app());

        foreach ($dependencies as $key => $value) {
            $dependencies[$key] = parse($value);
            info("setting dependency {$key} to <fg=magenta;options=bold>{$dependencies[$key]}</>");
        }

        $client->setSystemSoftwareVersions(
            AppRecipe::getAppInstallation()->getId(),
            $dependencies
        );
    }

}