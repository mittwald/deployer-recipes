<?php
declare(strict_types=1);

namespace Mittwald\Deployer\Util\SSH;

readonly class SSHPublicKey
{
    public function __construct(public string $publicKey, public string $comment)
    {
    }

    public static function fromString(string $publicKey): self
    {
        $sshPublicKeyParts               = explode(" ", $publicKey, limit: 3);
        if (count($sshPublicKeyParts) !== 3) {
            throw new \InvalidArgumentException("Invalid SSH public key");
        }

        $sshPublicKeyPartsWithoutComment = array_slice($sshPublicKeyParts, offset: 0, length: 2);
        $sshPublicKeyWithoutComment      = implode(" ", $sshPublicKeyPartsWithoutComment);

        return new self($sshPublicKeyWithoutComment, $sshPublicKeyParts[2]);
    }
}