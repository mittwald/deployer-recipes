<?php

namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreateIngress\IngressCreateIngress201Response;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreateIngress\IngressCreateIngressRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressCreateIngress\IngressCreateIngressRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressListIngresses\IngressListIngresses200Response;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressListIngresses\IngressListIngressesRequest;
use Mittwald\ApiClient\Generated\V2\Clients\Domain\IngressUpdateIngressPaths\IngressUpdateIngressPathsRequest;
use Mittwald\ApiClient\Generated\V2\Schemas\Ingress\Path;
use Mittwald\ApiClient\Generated\V2\Schemas\Ingress\TargetInstallation;
use function Deployer\info;
use function Deployer\parse;
use function Deployer\set;
use function Deployer\task;
use function Mittwald\Deployer\get_array;
use function Mittwald\Deployer\get_str;

class DomainRecipe
{
    private const DOMAIN_PATH_PREFIX = 'mittwald_domain_path_prefix';
    private const DOMAINS = 'mittwald_domains';

    public static function setup(): void
    {
        set(DomainRecipe::DOMAIN_PATH_PREFIX, '/');
        set(DomainRecipe::DOMAINS, ['{{domain}}']);

        task('mittwald:domain', [static::class, 'assertVirtualHosts'])
            ->desc('Assert that the domain is configured correctly on the mittwald platform');
    }

    public static function assertVirtualHosts(): void
    {
        /** @var string[] $domains */
        $domains = get_array(DomainRecipe::DOMAINS);
        $domains = array_map(fn(string $domain): string => parse($domain), $domains);

        foreach ($domains as $domain) {
            static::assertVirtualHost($domain);
        }
    }

    private static function assertVirtualHost(string $domain): void
    {
        $domainPathPrefix = get_str(DomainRecipe::DOMAIN_PATH_PREFIX);
        $client           = BaseRecipe::getClient()->domain();
        $project          = BaseRecipe::getProject();
        $app              = AppRecipe::getAppInstallation();

        $virtualHostResponse = $client->ingressListIngresses((new IngressListIngressesRequest())->withProjectId($project->getId()));
        if (!$virtualHostResponse instanceof IngressListIngresses200Response) {
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

            $request  = new IngressCreateIngressRequest((new IngressCreateIngressRequestBody(
                $domain,
                [new Path($domainPathPrefix, new TargetInstallation($app->getId()))],
                $project->getId(),
            )));
            $response = $client->ingressCreateIngress($request);

            if (!$response instanceof IngressCreateIngress201Response) {
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

                $request  = new IngressUpdateIngressPathsRequest($virtualHost->getId(), $updatedPaths);
                $response = $client->ingressUpdateIngressPaths($request);

                if (!$response instanceof EmptyResponse) {
                    throw new \Exception('could not update virtual host');
                }
            } else {
                info("virtual host <fg=magenta;options=bold>{$domain}</> exists, no update required");
            }
        }
    }

}