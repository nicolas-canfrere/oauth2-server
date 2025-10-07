<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\Key\Service\RSAKeyGeneratorHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\Key\Service\RSAKeyGeneratorHandler
 */
final class RSAKeyGeneratorHandlerTest extends TestCase
{
    private RSAKeyGeneratorHandler $service;

    protected function setUp(): void
    {
        $this->service = new RSAKeyGeneratorHandler();
    }

    public function testGenerateRsaKeyPairReturnsArrayWithPrivateAndPublicKeys(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertIsString($keyPair->privateKey);
        $this->assertIsString($keyPair->publicKey);
        $this->assertNotEmpty($keyPair->privateKey);
        $this->assertNotEmpty($keyPair->publicKey);
    }

    public function testGenerateRsaKeyPairReturnsValidPemFormattedPrivateKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $keyPair->privateKey);
        $this->assertStringContainsString('-----END PRIVATE KEY-----', $keyPair->privateKey);
    }

    public function testGenerateRsaKeyPairReturnsValidPemFormattedPublicKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $keyPair->publicKey);
        $this->assertStringContainsString('-----END PUBLIC KEY-----', $keyPair->publicKey);
    }

    public function testGenerateRsaKeyPairGenerates4096BitKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');

        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertArrayHasKey('bits', $keyDetails);
        $this->assertSame(4096, $keyDetails['bits'], 'RSA key should be 4096 bits');
    }

    public function testGenerateRsaKeyPairGeneratesRsaTypeKey(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $this->assertNotFalse($privateKeyResource, 'Failed to parse generated private key');

        $keyDetails = openssl_pkey_get_details($privateKeyResource);
        $this->assertIsArray($keyDetails);
        $this->assertArrayHasKey('type', $keyDetails);
        $this->assertSame(OPENSSL_KEYTYPE_RSA, $keyDetails['type'], 'Key should be RSA type');
    }

    public function testGenerateRsaKeyPairPublicKeyMatchesPrivateKey(): void
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

    public function testGenerateRsaKeyPairGeneratesUniqueKeysOnEachCall(): void
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

    public function testGeneratedKeyPairSupportsRs256Algorithm(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test RS256 (SHA256)
        $data = 'test RS256';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);

        $this->assertSame(1, $verifyResult, 'RS256 algorithm not supported');
    }

    public function testGeneratedKeyPairSupportsRs384Algorithm(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test RS384 (SHA384)
        $data = 'test RS384';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA384);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA384);

        $this->assertSame(1, $verifyResult, 'RS384 algorithm not supported');
    }

    public function testGeneratedKeyPairSupportsRs512Algorithm(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $privateKeyResource = openssl_pkey_get_private($keyPair->privateKey);
        $publicKeyResource = openssl_pkey_get_public($keyPair->publicKey);

        $this->assertNotFalse($privateKeyResource);
        $this->assertNotFalse($publicKeyResource);

        // Test RS512 (SHA512)
        $data = 'test RS512';
        $signature = '';
        $signSuccess = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA512);
        $this->assertTrue($signSuccess);
        $this->assertIsString($signature);

        $verifyResult = openssl_verify($data, $signature, $publicKeyResource, OPENSSL_ALGO_SHA512);

        $this->assertSame(1, $verifyResult, 'RS512 algorithm not supported');
    }
}
