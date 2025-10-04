<?php

declare(strict_types=1);

namespace App\OAuth2\Service;

use App\OAuth2\DTO\JwtPayloadDTO;
use App\Repository\KeyRepositoryInterface;
use App\Service\PrivateKeyEncryptionServiceInterface;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

/**
 * JWT token generator using web-token/jwt-framework.
 *
 * Generates signed JWT access tokens with the following features:
 * - Retrieves active cryptographic key from KeyRepository
 * - Decrypts private key using PrivateKeyEncryptionService
 * - Signs JWT with RSA (RS256/RS384/RS512) or ECDSA (ES256/ES384/ES512)
 * - Returns token in compact format: header.payload.signature
 * - Includes kid (Key ID) in JWT header for key rotation support
 */
final readonly class JwtTokenGenerator implements JwtTokenGeneratorInterface
{
    private AlgorithmManager $algorithmManager;
    private CompactSerializer $serializer;

    public function __construct(
        private KeyRepositoryInterface $keyRepository,
        private PrivateKeyEncryptionServiceInterface $privateKeyEncryption,
        private string $issuer,
    ) {
        // Initialize algorithm manager with all supported signature algorithms
        $this->algorithmManager = new AlgorithmManager([
            new RS256(),
            new RS384(),
            new RS512(),
            new ES256(),
            new ES384(),
            new ES512(),
        ]);

        // Initialize compact serializer for JWT output
        $this->serializer = new CompactSerializer();
    }

    public function generate(JwtPayloadDTO $payload): string
    {
        // Retrieve the active signing key from database
        $activeKeys = $this->keyRepository->findActiveKeys();

        if (empty($activeKeys)) {
            throw new \RuntimeException('No active cryptographic key found for JWT signing');
        }

        // Use the most recent active key (first in the list)
        $oauthKey = $activeKeys[0];

        // Decrypt the private key
        $privateKeyPem = $this->privateKeyEncryption->decrypt($oauthKey->privateKeyEncrypted);

        // Convert PEM private key to JWK format
        $jwk = $this->createJwkFromPem($privateKeyPem, $oauthKey->algorithm, $oauthKey->kid);

        // Convert payload DTO to JWT claims
        $claims = $payload->toClaims($this->issuer);

        // Build the JWS (JSON Web Signature)
        $jwsBuilder = new JWSBuilder($this->algorithmManager);

        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($jwk, [
                'alg' => $oauthKey->algorithm,
                'kid' => $oauthKey->kid,
                'typ' => 'JWT',
            ])
            ->build();

        // Serialize to compact format (header.payload.signature)
        return $this->serializer->serialize($jws, 0);
    }

    /**
     * Create a JWK (JSON Web Key) from a PEM-formatted private key.
     *
     * @param string $privateKeyPem The private key in PEM format
     * @param string $algorithm     The signature algorithm (RS256, ES256, etc.)
     * @param string $kid           The Key ID
     *
     * @return \Jose\Component\Core\JWK The JWK object ready for signing
     *
     * @throws \RuntimeException If key creation fails
     */
    private function createJwkFromPem(string $privateKeyPem, string $algorithm, string $kid): \Jose\Component\Core\JWK
    {
        try {
            // Determine key type from algorithm
            $keyType = $this->getKeyTypeFromAlgorithm($algorithm);

            // Create JWK from PEM using JWKFactory
            $jwk = JWKFactory::createFromKey(
                $privateKeyPem,
                null, // No password for decrypted keys
                [
                    'use' => 'sig',
                    'alg' => $algorithm,
                    'kid' => $kid,
                ]
            );

            // Validate that the key type matches the algorithm
            $actualKeyType = $jwk->get('kty');
            if (!\is_string($actualKeyType)) {
                throw new \RuntimeException('Invalid key type: expected string, got ' . get_debug_type($actualKeyType));
            }
            if ($actualKeyType !== $keyType) {
                throw new \RuntimeException(
                    sprintf(
                        'Key type mismatch: expected %s for algorithm %s, got %s',
                        $keyType,
                        $algorithm,
                        $actualKeyType
                    )
                );
            }

            return $jwk;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf('Failed to create JWK from PEM: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * Determine the expected JWK key type from the signature algorithm.
     *
     * @param string $algorithm The signature algorithm (RS256, ES256, etc.)
     *
     * @return string The JWK key type (RSA or EC)
     */
    private function getKeyTypeFromAlgorithm(string $algorithm): string
    {
        return match (true) {
            str_starts_with($algorithm, 'RS') => 'RSA',
            str_starts_with($algorithm, 'ES') => 'EC',
            default => throw new \RuntimeException(sprintf('Unsupported algorithm: %s', $algorithm)),
        };
    }
}
