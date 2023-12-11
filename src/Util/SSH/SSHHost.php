<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util\SSH;

readonly class SSHHost
{
    public function __construct(public string $name, public string $hostname, public ?string $identityFile = null)
    {
    }

    public function withIdentityFile(string $identityFile): self
    {
        return new self($this->name, $this->hostname, identityFile: $identityFile);
    }
}