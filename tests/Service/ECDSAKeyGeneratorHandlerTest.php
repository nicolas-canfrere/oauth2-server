<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;
use App\Domain\Key\Service\ECDSAKeyGeneratorHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Key\Service\ECDSAKeyGeneratorHandler
 */
final class ECDSAKeyGeneratorHandlerTest extends TestCase
{
    private ECDSAKeyGeneratorHandler $service;

    protected function setUp(): void
    {
        $this->service = new ECDSAKeyGeneratorHandler();
    }

    public function testConstructorThrowsExceptionForUnsupportedCurve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported ECDSA curve "invalid"');

        new ECDSAKeyGeneratorHandler('invalid');
    }

    public function testGenerateKeyPairReturnsArrayWithPrivateAndPublicKeys(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertIsString($keyPair->privateKey);
        $this->assertIsString($keyPair->publicKey);
        $this->assertNotEmpty($keyPair->privateKey);
        $this->assertNotEmpty($keyPair->publicKey);
    }

    public function testGenerateKeyPairReturnsValidPemFormattedPrivateKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $keyPair->privateKey);
        $this->assertStringContainsString('-----END PRIVATE KEY-----', $keyPair->privateKey);
    }

    public function testGenerateKeyPairReturnsValidPemFormattedPublicKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $keyPair->publicKey);
        $this->assertStringContainsString('-----END PUBLIC KEY-----', $keyPair->publicKey);
    }

    public function testGenerateKeyPairGeneratesEcTypeKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');

        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertArrayHasKey('type', $keyDetails);
        $this->assertSame(OPENSSL_KEYTYPE_EC, $keyDetails['type'], 'Key should be EC type');
    }

    public function testGenerateKeyPairDefaultsToP256Curve(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');
        /** @var array{ec: array{curve_name: string}}|false $keyDetails */
        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertSame('prime256v1', $keyDetails['ec']['curve_name'], 'Default curve should be prime256v1 (P-256)');
    }

    public function testGenerateKeyPairWithP384Curve(): void
    {
        $service = new ECDSAKeyGeneratorHandler('secp384r1');
        $keyPair = $service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');
        /** @var array{ec: array{curve_name: string}}|false $keyDetails */
        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertSame('secp384r1', $keyDetails['ec']['curve_name'], 'Curve should be secp384r1 (P-384)');
    }

    public function testGenerateKeyPairWithP521Curve(): void
    {
        $service = new ECDSAKeyGeneratorHandler('secp521r1');
        $keyPair = $service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');
        /** @var array{ec: array{curve_name: string}}|false $keyDetails */
        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertSame('secp521r1', $keyDetails['ec']['curve_name'], 'Curve should be secp521r1 (P-521)');
    }

    public function testGenerateKeyPairPublicKeyMatchesPrivateKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');

        $extractedPublicKeyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($extractedPublicKeyDetails);
        $this->assertArrayHasKey('key', $extractedPublicKeyDetails);

        // The public key from the array should match the one extracted from the private key
        $this->assertSame($extractedPublicKeyDetails['key'], $keyPair->publicKey);
    }

    public function testGenerateKeyPairGeneratesUniqueKeysOnEachCall(): void
    {
        $keyPair1 = $this->service->generateKeyPair();
        $keyPair2 = $this->service->generateKeyPair();

        $this->assertNotEquals($keyPair1->privateKey, $keyPair2->privateKey);
        $this->assertNotEquals($keyPair1->publicKey, $keyPair2->publicKey);
    }

    public function testGeneratedKeyPairCanBeUsedForSigningAndVerification(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $data = 'test data to sign';
        $signature = '';

        // Sign with private key
        $signSuccess = openssl_sign($data, $signature, $keyPair->privateKey, OPENSSL_ALGO_SHA256);
        $this->assertTrue($signSuccess, 'Failed to sign data with generated private key');
        $this->assertIsString($signature);

        // Verify with public key
        $verifyResult = openssl_verify($data, $signature, $keyPair->publicKey, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verifyResult, 'Signature verification failed with generated public key');
    }

    public function testGeneratedKeyPairSupportsEs256Algorithm(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test ES256 (SHA256 with P-256)
        $data = 'test ES256';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);

        $this->assertSame(1, $verifyResult, 'ES256 algorithm not supported');
    }

    public function testGeneratedKeyPairSupportsEs384Algorithm(): void
    {
        $service = new ECDSAKeyGeneratorHandler('secp384r1');
        $keyPair = $service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test ES384 (SHA384 with P-384)
        $data = 'test ES384';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA384);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA384);

        $this->assertSame(1, $verifyResult, 'ES384 algorithm not supported');
    }

    public function testGeneratedKeyPairSupportsEs512Algorithm(): void
    {
        $service = new ECDSAKeyGeneratorHandler('secp521r1');
        $keyPair = $service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test ES512 (SHA512 with P-521)
        $data = 'test ES512';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA512);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA512);

        $this->assertSame(1, $verifyResult, 'ES512 algorithm not supported');
    }

    public function testSupportsReturnsTrueForEcdsaAlgorithm(): void
    {
        $this->assertTrue($this->service->supports(KeyAlgorithmEnum::ECDSA));
    }

    public function testSupportsReturnsFalseForRsaAlgorithm(): void
    {
        $this->assertFalse($this->service->supports(KeyAlgorithmEnum::RSA));
    }
}
