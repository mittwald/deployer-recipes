<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util\SSH;

use League\Flysystem\Filesystem;

readonly class SSHConfigRenderer
{
    public function __construct(private SSHConfig $config)
    {
    }

    public function render(): string
    {
        $output = "";

        foreach ($this->config->hosts as $host) {
            $output .= "Host {$host->name}\n";
            $output .= "    HostName {$host->hostname}\n";
            $output .= "    StrictHostKeyChecking accept-new\n";

            if ($host->identityFile !== null) {
                $output .= "    IdentityFile {$host->identityFile}\n";
            }

            $output .= "\n";
        }

        return $output;
    }

    public function renderToFile(Filesystem $fs): void
    {
        $fs->write($this->config->filename, $this->render());
    }
}