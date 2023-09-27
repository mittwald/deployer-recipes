<?php

namespace Mittwald\Deployer\Client;

use Composer\Semver\Comparator;
use Mittwald\ApiClient\Client\EmptyResponse;
use Mittwald\ApiClient\Generated\V2\Clients\App\AppClient as GeneratedAppClient;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallation200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\GetAppinstallation\GetAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwares\ListSystemsoftwares200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwares\ListSystemsoftwaresRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwareversions\ListSystemsoftwareversions200Response;
use Mittwald\ApiClient\Generated\V2\Clients\App\ListSystemsoftwareversions\ListSystemsoftwareversionsRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequest;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequestBody;
use Mittwald\ApiClient\Generated\V2\Clients\App\PatchAppinstallation\PatchAppinstallationRequestBodySystemSoftwareItem;
use Mittwald\ApiClient\Generated\V2\Schemas\App\SystemSoftware;
use Mittwald\ApiClient\Generated\V2\Schemas\App\SystemSoftwareUpdatePolicy;
use Mittwald\ApiClient\Generated\V2\Schemas\App\SystemSoftwareVersion;
use Throwable;

class AppClient
{
    public function __construct(private readonly GeneratedAppClient $inner)
    {
    }

    /**
     * @param string $appInstallationId
     * @param array<non-empty-string, string> $systemSoftwareConstraints
     * @return void
     * @throws Throwable
     */
    public function setSystemSoftwareVersions(string $appInstallationId, array $systemSoftwareConstraints): void
    {
        $appInstallationResponse = $this->inner->getAppinstallation(new GetAppinstallationRequest($appInstallationId));
        if (!$appInstallationResponse instanceof GetAppinstallation200Response) {
            throw new \Exception('could not get app installation');
        }

        $systemSoftwareSpec = [];

        foreach ($systemSoftwareConstraints as $name => $constraint) {
            [$systemSoftware, $version] = $this->resolveSystemSoftwareByConstraint($name, $constraint);
            $systemSoftwareSpec[$systemSoftware->getId()] = (new PatchAppinstallationRequestBodySystemSoftwareItem())
                ->withSystemSoftwareVersion($version->getId())
                ->withUpdatePolicy(SystemSoftwareUpdatePolicy::patchLevel);
        }

        $appInstallationRequest = new PatchAppinstallationRequest(
            $appInstallationId,
            (new PatchAppinstallationRequestBody())
                ->withSystemSoftware($systemSoftwareSpec)
        );

        $patchAppInstallationResponse = $this->inner->patchAppinstallation($appInstallationRequest);
        if (!$patchAppInstallationResponse instanceof EmptyResponse) {
            throw new \Exception('could not patch app installation');
        }
    }

    /**
     * @param string $systemSoftwareName
     * @param string $systemSoftwareVersionConstraint
     * @return list{SystemSoftware,SystemSoftwareVersion}
     * @throws Throwable
     */
    public function resolveSystemSoftwareByConstraint(string $systemSoftwareName, string $systemSoftwareVersionConstraint): array
    {
        $systemSoftwareVersionConstraint = static::normalizeVersionConstraint($systemSoftwareVersionConstraint);

        $systemSoftwareResponse = $this->inner->listSystemsoftwares(new ListSystemsoftwaresRequest());
        if (!$systemSoftwareResponse instanceof ListSystemsoftwares200Response) {
            throw new \Exception('could not list system software');
        }

        $systemSoftware = (function () use ($systemSoftwareResponse, $systemSoftwareName): SystemSoftware {
            foreach ($systemSoftwareResponse->getBody() as $systemSoftware) {
                if (strtolower($systemSoftware->getName()) === strtolower($systemSoftwareName)) {
                    return $systemSoftware;
                }
            }
            throw new \Exception("could not find system software {$systemSoftwareName}");
        })();

        $versionResponse = $this->inner->listSystemsoftwareversions(
            (new ListSystemsoftwareversionsRequest($systemSoftware->getId()))
                ->withVersionRange($systemSoftwareVersionConstraint)
        );
        if (!$versionResponse instanceof ListSystemsoftwareVersions200Response) {
            throw new \Exception('could not list system software versions');
        }

        $newest = null;

        foreach ($versionResponse->getBody() as $version) {
            if ($newest === null || Comparator::greaterThan($version->getInternalVersion(), $newest->getInternalVersion())) {
                $newest = $version;
            }
        }

        if ($newest === null) {
            throw new \Exception("could not find system software version matching {$systemSoftwareVersionConstraint}");
        }

        return [$systemSoftware, $newest];
    }

    private static function normalizeVersionConstraint(string $constraint): string
    {
        if (preg_match('/^[0-9]+\.[0-9]+/', $constraint)) {
            return "{$constraint}.*";
        }
        return $constraint;
    }
}