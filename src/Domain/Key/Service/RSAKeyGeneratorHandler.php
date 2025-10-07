<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;

/**
 * OpenSSL-based implementation for generating RSA 4096-bit key pairs.
 *
 * Generates cryptographically secure RSA key pairs suitable for
 * RS256, RS384, and RS512 JWT signing algorithms.
 */
final class RSAKeyGeneratorHandler implements KeyGeneratorHandlerInterface
{
    private const RSA_KEY_BITS = 4096;
    private const RSA_KEY_TYPE = OPENSSL_KEYTYPE_RSA;

    public function generateKeyPair(): KeyPairDTO
    {
        // Configure RSA key generation parameters
        $config = [
            'private_key_bits' => self::RSA_KEY_BITS,
            'private_key_type' => self::RSA_KEY_TYPE,
        ];

        // Generate the private key resource
        $privateKeyResource = openssl_pkey_new($config);

        if (false === $privateKeyResource) {
            throw new \RuntimeException(
                sprintf('Failed to generate RSA key pair: %s', openssl_error_string())
            );
        }

        // Export private key to PEM format
        /** @var string $privateKeyPem */
        $privateKeyPem = '';
        $exportSuccess = openssl_pkey_export($privateKeyResource, $privateKeyPem);

        if (false === $exportSuccess || !is_string($privateKeyPem) || '' === $privateKeyPem) {
            throw new \RuntimeException(
                sprintf('Failed to export private key: %s', openssl_error_string())
            );
        }

        // Extract public key from private key resource
        $publicKeyDetails = openssl_pkey_get_details($privateKeyResource);

        if (false === $publicKeyDetails || !isset($publicKeyDetails['key']) || !is_string($publicKeyDetails['key'])) {
            throw new \RuntimeException(
                sprintf('Failed to extract public key: %s', openssl_error_string())
            );
        }

        return new KeyPairDTO($publicKeyDetails['key'], $privateKeyPem);
    }

    public function supports(KeyAlgorithmEnum $algorithm): bool
    {
        return KeyAlgorithmEnum::RSA === $algorithm;
    }
}
