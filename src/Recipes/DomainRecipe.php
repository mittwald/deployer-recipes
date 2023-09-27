<?php

namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreate\IngressCreate201Response;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreate\IngressCreateRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreate\IngressCreateRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressListForProject\IngressListForProject200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressListForProject\IngressListForProjectRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressPaths\IngressPathsRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\Ingress\Path;
use Mittwald\ApiClient\Generated\V2\Schemas\Ingress\TargetInstallation;
use function Deployer\get;
use function Deployer\info;
use function Deployer\parse;
use function Deployer\set;
use function Deployer\task;

class DomainRecipe
{
    private const DOMAIN_PATH_PREFIX = 'mittwald_domain_path_prefix';
    private const DOMAINS = 'mittwald_domains';

    public static function setup()
    {
        set(static::DOMAIN_PATH_PREFIX, '/');
        set(static::DOMAINS, ['{{domain}}']);

        task('mittwald:domain', [static::class, 'assertVirtualHosts'])
            ->desc('Assert that the domain is configured correctly on the mittwald platform');
    }

    public static function assertVirtualHosts(): void
    {
        $domains = get(static::DOMAINS);
        $domains = array_map(fn($domain) => parse($domain), $domains);

        foreach ($domains as $domain) {
            static::assertVirtualHost($domain);
        }
    }

    private static function assertVirtualHost(string $domain): void
    {
        $domainPathPrefix = get(static::DOMAIN_PATH_PREFIX);
        $client           = BaseRecipe::getClient()->domain();
        $project          = BaseRecipe::getProject();
        $app              = AppRecipe::getAppInstallation();

        $virtualHostResponse = $client->ingressListForProject(new IngressListForProjectRequest($project->getId()));
        if (!$virtualHostResponse instanceof IngressListForProject200Response) {
            throw new \Exception('could not list virtual hosts');
        }

        $virtualHost = (function () use ($virtualHostResponse, $domain) {
            foreach ($virtualHostResponse->getBody() as $virtualHost) {
                if ($virtualHost->getHostname() === $domain) {
                    return $virtualHost;
                }
            }
            return null;
        })();

        if ($virtualHost === null) {
            info("virtual host <fg=magenta;options=bold>{$domain}</> does not exist, creating it");

            $request  = new IngressCreateRequest((new IngressCreateRequestBody(
                $domain,
                [new Path($domainPathPrefix, new TargetInstallation($app->getId()))],
                $project->getId(),
            )));
            $response = $client->ingressCreate($request);

            if (!$response instanceof IngressCreate201Response) {
                throw new \Exception('could not create virtual host');
            }
        } else {

            $updatedPaths   = (clone $virtualHost)->getPaths();
            $found          = false;
            $updateRequired = false;

            foreach ($updatedPaths as $idx => $path) {
                if ($path->getPath() === $domainPathPrefix) {
                    $found          = true;
                    $existingTarget = $path->getTarget();

                    if (!($existingTarget instanceof TargetInstallation && $existingTarget->getInstallationId() === $app->getId())) {
                        $updatedPaths[$idx] = $path->withTarget(new TargetInstallation($app->getId()));
                        $updateRequired     = true;
                    }
                }
            }

            if (!$found) {
                $updatedPaths[] = new Path($domainPathPrefix, new TargetInstallation($app->getId()));
                $updateRequired = true;
            }

            if ($updateRequired) {
                info("virtual host <fg=magenta;options=bold>{$domain}</> exists, updating it");

                $request  = new IngressPathsRequest($virtualHost->getId(), $updatedPaths);
                $response = $client->ingressPaths($request);

                if (!$response instanceof EmptyResponse) {
                    throw new \Exception('could not update virtual host');
                }
            } else {
                info("virtual host <fg=magenta;options=bold>{$domain}</> exists, no update required");
            }
        }
    }

}