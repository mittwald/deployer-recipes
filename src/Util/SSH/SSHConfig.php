<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util\SSH;

class SSHConfig
{
    /**
     * @var SSHHost[]
     */
    public array $hosts = [];

    public function __construct(public readonly string $filename)
    {
    }

    public function withHost(SSHHost $host): self
    {
        $c = clone $this;
        $c->hosts[] = $host;

        return $c;
    }
}