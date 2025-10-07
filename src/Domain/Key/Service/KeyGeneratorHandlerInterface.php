<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;

/**
 * Interface for generating RSA key pairs for OAuth2 JWT signing.
 *
 * Generates RSA 4096-bit key pairs suitable for RS256, RS384, and RS512 algorithms.
 * Keys are generated in PEM format using OpenSSL.
 */
interface KeyGeneratorHandlerInterface
{
    /**
     * Generate an RSA 4096-bit key pair.
     *
     * @throws \RuntimeException If key generation fails
     */
    public function generateKeyPair(): KeyPairDTO;

    public function supports(KeyAlgorithmEnum $algorithm): bool;
}
