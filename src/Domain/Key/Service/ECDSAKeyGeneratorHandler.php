<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;

/**
 * OpenSSL-based implementation for generating ECDSA key pairs.
 *
 * Generates cryptographically secure ECDSA key pairs suitable for
 * ES256 (P-256), ES384 (P-384), and ES512 (P-521) JWT signing algorithms.
 *
 * ECDSA provides equivalent security to RSA with smaller key sizes:
 * - P-256 (256-bit) ≈ RSA 3072-bit
 * - P-384 (384-bit) ≈ RSA 7680-bit
 * - P-521 (521-bit) ≈ RSA 15360-bit
 */
final class ECDSAKeyGeneratorHandler implements KeyGeneratorHandlerInterface
{
    private const DEFAULT_CURVE = 'prime256v1'; // P-256 for ES256

    /**
     * Mapping of curve names to OpenSSL identifiers.
     */
    private const SUPPORTED_CURVES = [
        'P-256' => 'prime256v1',  // ES256
        'P-384' => 'secp384r1',   // ES384
        'P-521' => 'secp521r1',   // ES512
    ];

    public function __construct(
        private readonly string $curve = self::DEFAULT_CURVE,
    ) {
        if (!in_array($this->curve, self::SUPPORTED_CURVES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported ECDSA curve "%s". Supported curves: %s',
                    $this->curve,
                    implode(', ', array_keys(self::SUPPORTED_CURVES))
                )
            );
        }
    }

    public function generateKeyPair(): KeyPairDTO
    {
        // Configure ECDSA key generation parameters
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => $this->curve,
        ];

        // Generate the private key resource
        $privateKeyResource = openssl_pkey_new($config);

        if (false === $privateKeyResource) {
            throw new \RuntimeException(
                sprintf('Failed to generate ECDSA key pair: %s', openssl_error_string())
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
        return KeyAlgorithmEnum::ECDSA === $algorithm;
    }
}
