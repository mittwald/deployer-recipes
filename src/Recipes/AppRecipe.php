<?php
namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallation200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListAppinstallations\ListAppinstallations200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListAppinstallations\ListAppinstallationsRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProject200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Project\GetProject\GetProjectRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ListProjects\ListProjects200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Project\ListProjects\ListProjectsRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\App\AppInstallation;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\MittwaldAPIV2Client;
use function Deployer\{get, set, Support\starts_with};

class AppRecipe
{
    public static function set()
    {
        set('mittwald_token', function (): string {
            $token = getenv('MITTWALD_API_TOKEN');
            if (!$token) {
                throw new \Exception('MITTWALD_API_TOKEN not set');
            }

            return $token;
        });

        set('mittwald_project_id', function (): string {
            if ($app = static::getAppInstallation()) {
                if ($projectId = $app->getProjectId()) {
                    set('mittwald_project_id', $projectId);
                    return $projectId;
                }
            }

            throw new \Exception('please set mittwald_project_id when mittwald_app_id is _not_ set');
        });

        set('mittwald_project_uuid', function (): string {
            $client = static::getClient()->project();
            $projectId = get('mittwald_project_id');

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
            $client = static::getClient();
            $projectId = get('mittwald_project_uuid');

            $projectRequest = new GetProjectRequest($projectId);
            $projectResponse = $client->project()->getProject($projectRequest);
            if (!$projectResponse instanceof GetProject200Response) {
                throw new \Exception('could not get projects');
            }

            return $projectResponse->getBody()->toJson();
        });

        set('mittwald_app', function (): array {
            $client = static::getClient()->app();

            if ($appID = get('mittwald_app_id')) {
                $appResponse = $client->getAppinstallation(new GetAppinstallationRequest($appID));
                if (!$appResponse instanceof GetAppinstallation200Response) {
                    throw new \Exception('could not get app');
                }

                return $appResponse->getBody()->toJson();
            }

            if ($deployPath = get('deploy_path')) {
                $project = static::getProject();

                $appsResponse = $client->listAppinstallations(new ListAppinstallationsRequest($project->getId()));
                if (!$appsResponse instanceof ListAppinstallations200Response) {
                    throw new \Exception('could not list apps');
                }

                $webBasePath = $project->getDirectories()["Web"];

                foreach ($appsResponse->getBody() as $app) {
                    if ($webBasePath . $app->getInstallationPath() === $deployPath) {
                        return $app;
                    }
                }

                throw new \Exception("could not find app with installation path {$deployPath}");
            }

            throw new \Exception("neither mittwald_app_id nor deploy_path set");
        });

        set('mittwald_app_dependencies', [
            'php' => '{{php_version}}'
        ]);
    }

    /**
     * @deprecated Use BaseRecipe::getClient instead!
     */
    public static function getClient(): MittwaldAPIV2Client
    {
        return BaseRecipe::getClient();
    }

    public static function getAppInstallation(): AppInstallation
    {
        return AppInstallation::buildFromInput(get('mittwald_app'), validate: false);
    }

    /**
     * @deprecated Use BaseRecipe::getProject instead!
     */
    public static function getProject(): Project
    {
        return BaseRecipe::getProject();
    }
}