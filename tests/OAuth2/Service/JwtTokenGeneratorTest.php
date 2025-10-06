<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\Service;

use App\Application\AccessToken\DTO\JwtPayloadDTO;
use App\Domain\Key\Model\OAuthKey;
use App\Domain\Key\Repository\KeyRepositoryInterface;
use App\Domain\Key\Service\PrivateKeyEncryptionServiceInterface;
use App\Infrastructure\AccessToken\Service\JwtTokenGenerator;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\AccessToken\Service\JwtTokenGenerator
 */
final class JwtTokenGeneratorTest extends TestCase
{
    private const ISSUER = 'http://localhost:8000';

    private KeyRepositoryInterface $keyRepository;
    private PrivateKeyEncryptionServiceInterface $privateKeyEncryption;
    private JwtTokenGenerator $generator;

    protected function setUp(): void
    {
        /** @var KeyRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $keyRepository */
        $keyRepository = $this->createMock(KeyRepositoryInterface::class);
        $this->keyRepository = $keyRepository;

        /** @var PrivateKeyEncryptionServiceInterface&\PHPUnit\Framework\MockObject\MockObject $privateKeyEncryption */
        $privateKeyEncryption = $this->createMock(PrivateKeyEncryptionServiceInterface::class);
        $this->privateKeyEncryption = $privateKeyEncryption;

        $this->generator = new JwtTokenGenerator(
            $this->keyRepository,
            $this->privateKeyEncryption,
            self::ISSUER
        );
    }

    public function testGenerateCreatesValidJwtWithRS256(): void
    {
        // Arrange: Create test RSA key pair
        [$privateKeyPem, $publicKeyPem, $kid] = $this->generateRsaKeyPair();

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'RS256',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository
            ->expects($this->once())
            ->method('findActiveKeys')
            ->willReturn([$oauthKey]);

        $this->privateKeyEncryption
            ->expects($this->once())
            ->method('decrypt')
            ->with('encrypted-private-key')
            ->willReturn($privateKeyPem);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read write',
            expiresIn: 3600,
            clientId: 'client-456'
        );

        // Act
        $token = $this->generator->generate($payload);

        // Assert
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify JWT structure (header.payload.signature)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT should have 3 parts separated by dots');

        // Decode and verify header
        $headerDecoded = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $this->assertIsString($headerDecoded);
        /** @var array<string, mixed> $header */
        $header = json_decode($headerDecoded, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('RS256', $header['alg']);
        $this->assertSame($kid, $header['kid']);
        $this->assertSame('JWT', $header['typ']);

        // Decode and verify payload claims
        $claimsDecoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $this->assertIsString($claimsDecoded);
        /** @var array<string, mixed> $claims */
        $claims = json_decode($claimsDecoded, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(self::ISSUER, $claims['iss']);
        $this->assertSame('user-123', $claims['sub']);
        $this->assertSame('client-456', $claims['aud']);
        $this->assertSame('read write', $claims['scope']);
        $this->assertSame('client-456', $claims['client_id']);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('jti', $claims);

        // Verify signature
        $this->verifyJwtSignature($token, $publicKeyPem, 'RS256');
    }

    public function testGenerateCreatesValidJwtWithRS384(): void
    {
        [$privateKeyPem, $publicKeyPem, $kid] = $this->generateRsaKeyPair();

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'RS384',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey]);
        $this->privateKeyEncryption->method('decrypt')->willReturn($privateKeyPem);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read',
            expiresIn: 3600
        );

        $token = $this->generator->generate($payload);

        $this->assertIsString($token);
        $this->verifyJwtSignature($token, $publicKeyPem, 'RS384');
    }

    public function testGenerateCreatesValidJwtWithRS512(): void
    {
        [$privateKeyPem, $publicKeyPem, $kid] = $this->generateRsaKeyPair();

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'RS512',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey]);
        $this->privateKeyEncryption->method('decrypt')->willReturn($privateKeyPem);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'admin',
            expiresIn: 1800
        );

        $token = $this->generator->generate($payload);

        $this->assertIsString($token);
        $this->verifyJwtSignature($token, $publicKeyPem, 'RS512');
    }

    public function testGenerateCreatesValidJwtWithES256(): void
    {
        // Use real ECDSA key generated with openssl CLI (P-256 curve)
        // Note: We use hardcoded keys instead of generating them with openssl_pkey_new() because
        // PHP's OpenSSL extension produces EC keys in a format that is sometimes incompatible
        // with web-token/jwt-framework's JWKFactory::createFromKey(). This is a known limitation
        // of the PHP OpenSSL extension, not of our production code. In production, ECDSA keys
        // will be generated by professional tools (openssl CLI, HashiCorp Vault, HSM) and will
        // work correctly with JwtTokenGenerator.
        $privateKeyPem = <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIKBQoMKxb8h5GeJy1Y9DCrkmfZWViUrS91uSSfgzyYtRoAoGCCqGSM49
AwEHoUQDQgAEPSi/K7bKRGZyW1/+9zyyQ7sObbZgh5DHli9rvhTgkpShpT+ZxUbc
8K/wH2RN9rk9t4VrG1p0683GBz2K4Plwpg==
-----END EC PRIVATE KEY-----
PEM;

        $publicKeyPem = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEPSi/K7bKRGZyW1/+9zyyQ7sObbZg
h5DHli9rvhTgkpShpT+ZxUbc8K/wH2RN9rk9t4VrG1p0683GBz2K4Plwpg==
-----END PUBLIC KEY-----
PEM;

        $kid = 'test-ec-' . bin2hex(random_bytes(8));

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'ES256',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey]);
        $this->privateKeyEncryption->method('decrypt')->willReturn($privateKeyPem);

        $payload = new JwtPayloadDTO(
            subject: 'user-789',
            audience: 'client-abc',
            scope: 'profile email',
            expiresIn: 7200
        );

        $token = $this->generator->generate($payload);
        $this->assertIsString($token);
        $this->verifyJwtSignature($token, $publicKeyPem, 'ES256');
    }

    public function testGenerateThrowsExceptionWhenNoActiveKeyFound(): void
    {
        $this->keyRepository
            ->expects($this->once())
            ->method('findActiveKeys')
            ->willReturn([]);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read',
            expiresIn: 3600
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active cryptographic key found for JWT signing');

        $this->generator->generate($payload);
    }

    public function testGenerateUsesFirstActiveKey(): void
    {
        [$privateKey1, $publicKey1, $kid1] = $this->generateRsaKeyPair();
        [$privateKey2, $publicKey2, $kid2] = $this->generateRsaKeyPair();

        $oauthKey1 = new OAuthKey(
            id: 'key-1',
            kid: $kid1,
            algorithm: 'RS256',
            publicKey: $publicKey1,
            privateKeyEncrypted: 'encrypted-1',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $oauthKey2 = new OAuthKey(
            id: 'key-2',
            kid: $kid2,
            algorithm: 'RS256',
            publicKey: $publicKey2,
            privateKeyEncrypted: 'encrypted-2',
            isActive: true,
            createdAt: new \DateTimeImmutable('-1 day'),
            expiresAt: new \DateTimeImmutable('+89 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey1, $oauthKey2]);
        $this->privateKeyEncryption->method('decrypt')->with('encrypted-1')->willReturn($privateKey1);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read',
            expiresIn: 3600
        );

        $token = $this->generator->generate($payload);

        // Verify the first key was used
        $parts = explode('.', $token);
        $headerDecoded = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $this->assertIsString($headerDecoded);
        /** @var array<string, mixed> $header */
        $header = json_decode($headerDecoded, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($kid1, $header['kid']);
    }

    public function testGenerateIncludesOptionalNotBeforeClaim(): void
    {
        [$privateKeyPem, $publicKeyPem, $kid] = $this->generateRsaKeyPair();

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'RS256',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey]);
        $this->privateKeyEncryption->method('decrypt')->willReturn($privateKeyPem);

        $notBefore = time() + 300; // 5 minutes in the future
        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read',
            expiresIn: 3600,
            notBefore: $notBefore
        );

        $token = $this->generator->generate($payload);

        $parts = explode('.', $token);
        $claimsDecoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $this->assertIsString($claimsDecoded);
        /** @var array<string, mixed> $claims */
        $claims = json_decode($claimsDecoded, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('nbf', $claims);
        $this->assertSame($notBefore, $claims['nbf']);
    }

    public function testGenerateIncludesAdditionalClaims(): void
    {
        [$privateKeyPem, $publicKeyPem, $kid] = $this->generateRsaKeyPair();

        $oauthKey = new OAuthKey(
            id: 'test-key-id',
            kid: $kid,
            algorithm: 'RS256',
            publicKey: $publicKeyPem,
            privateKeyEncrypted: 'encrypted-private-key',
            isActive: true,
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+90 days')
        );

        $this->keyRepository->method('findActiveKeys')->willReturn([$oauthKey]);
        $this->privateKeyEncryption->method('decrypt')->willReturn($privateKeyPem);

        $payload = new JwtPayloadDTO(
            subject: 'user-123',
            audience: 'client-456',
            scope: 'read',
            expiresIn: 3600,
            additionalClaims: [
                'custom_claim' => 'custom_value',
                'tenant_id' => 'tenant-789',
            ]
        );

        $token = $this->generator->generate($payload);

        $parts = explode('.', $token);
        $claimsDecoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $this->assertIsString($claimsDecoded);
        /** @var array<string, mixed> $claims */
        $claims = json_decode($claimsDecoded, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('custom_claim', $claims);
        $this->assertSame('custom_value', $claims['custom_claim']);
        $this->assertArrayHasKey('tenant_id', $claims);
        $this->assertSame('tenant-789', $claims['tenant_id']);
    }

    /**
     * Verify JWT signature using public key.
     */
    private function verifyJwtSignature(string $token, string $publicKeyPem, string $algorithm): void
    {
        $algorithmManager = new AlgorithmManager([
            new RS256(),
            new RS384(),
            new RS512(),
            new ES256(),
        ]);

        $jwsVerifier = new JWSVerifier($algorithmManager);
        $serializer = new CompactSerializer();

        $jws = $serializer->unserialize($token);
        $jwk = JWKFactory::createFromKey($publicKeyPem);

        $isValid = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        $this->assertTrue($isValid, sprintf('JWT signature verification failed for algorithm %s', $algorithm));
    }

    /**
     * Generate RSA key pair for testing.
     *
     * @return array{0: string, 1: string, 2: string} [privateKeyPem, publicKeyPem, kid]
     */
    private function generateRsaKeyPair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if (false === $res) {
            throw new \RuntimeException('Failed to generate RSA key pair');
        }

        $privateKey = '';
        if (!openssl_pkey_export($res, $privateKey)) {
            throw new \RuntimeException('Failed to export private key');
        }
        if (!is_string($privateKey) || '' === $privateKey) {
            throw new \RuntimeException('Private key export failed: empty result');
        }

        $details = openssl_pkey_get_details($res);
        if (false === $details || !isset($details['key']) || !is_string($details['key'])) {
            throw new \RuntimeException('Failed to get key details');
        }

        $publicKey = $details['key'];
        $kid = 'test-rsa-' . bin2hex(random_bytes(8));

        return [$privateKey, $publicKey, $kid];
    }
}
