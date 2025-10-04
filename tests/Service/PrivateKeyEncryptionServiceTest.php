<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PrivateKeyEncryptionService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\PrivateKeyEncryptionService
 */
final class PrivateKeyEncryptionServiceTest extends TestCase
{
    // Valid 32-byte key encoded in base64
    private const VALID_ENCRYPTION_KEY = '8c8Lq3xg9K6WZM8P8SzyVI34/p+YE09z06rba8XalBU='; // 32 bytes base64

    private PrivateKeyEncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new PrivateKeyEncryptionService(self::VALID_ENCRYPTION_KEY);
    }

    public function testEncryptReturnsBase64EncodedString(): void
    {
        $privateKey = $this->generateTestRsaPrivateKey();

        $encrypted = $this->service->encrypt($privateKey);

        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($privateKey, $encrypted);

        // Verify it's valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $privateKey = $this->generateTestRsaPrivateKey();

        $encrypted = $this->service->encrypt($privateKey);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($privateKey, $decrypted);
    }

    public function testEncryptGeneratesDifferentCiphertextsForSameInput(): void
    {
        $privateKey = $this->generateTestRsaPrivateKey();

        $encrypted1 = $this->service->encrypt($privateKey);
        $encrypted2 = $this->service->encrypt($privateKey);

        // Due to random nonce, each encryption should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertSame($privateKey, $this->service->decrypt($encrypted1));
        $this->assertSame($privateKey, $this->service->decrypt($encrypted2));
    }

    public function testDecryptThrowsExceptionForInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode encrypted private key');

        $this->service->decrypt('not-valid-base64!!!');
    }

    public function testDecryptThrowsExceptionForInvalidDataLength(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data length');

        // Too short to contain nonce + tag
        $this->service->decrypt(base64_encode('short'));
    }

    public function testDecryptThrowsExceptionForTamperedData(): void
    {
        $privateKey = $this->generateTestRsaPrivateKey();
        $encrypted = $this->service->encrypt($privateKey);

        // Tamper with the encrypted data
        $decoded = base64_decode($encrypted, true);
        $this->assertIsString($decoded);
        $tampered = substr($decoded, 0, -1) . 'X'; // Modify last byte (authentication tag)
        $reencoded = base64_encode($tampered);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt private key: authentication tag verification failed');

        $this->service->decrypt($reencoded);
    }

    public function testConstructorThrowsExceptionForInvalidBase64Key(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PRIVATE_KEY_ENCRYPTION_KEY must be a valid base64-encoded string');

        new PrivateKeyEncryptionService('invalid-base64!!!');
    }

    public function testConstructorThrowsExceptionForWrongKeyLength(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PRIVATE_KEY_ENCRYPTION_KEY must be 32 bytes (256 bits) when decoded');

        // 16 bytes instead of 32
        $shortKey = base64_encode(random_bytes(16));
        new PrivateKeyEncryptionService($shortKey);
    }

    public function testEncryptDecryptWithEcdsaPrivateKey(): void
    {
        $ecPrivateKey = $this->generateTestEcdsaPrivateKey();

        $encrypted = $this->service->encrypt($ecPrivateKey);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($ecPrivateKey, $decrypted);
    }

    public function testEncryptEmptyStringThrowsException(): void
    {
        $encrypted = $this->service->encrypt('');
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    /**
     * Generate a test RSA private key in PEM format.
     */
    private function generateTestRsaPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAw7Zdfmece8iaUKdaKUmWLDJKbNKFSfKKDZjYLnQmwKlz4gCg
MlrXOl3C2Q7J3dHKCQZCxzLKMJrLz0DsYJnmGjvPFBP+Hq9nQPQRvJ2RfGZR5jN6
qHpRfNfQjGLFYjJqz6lHzRtjKZqmEKdB2p4N0K8h7T4X3R8aDfPqQjEhK9Q3r7Yn
gFKSQvDJZL9H4xFr6QvMmVrjfJ5cQqNrDqGq7bKJqLhDN9QqR2PqDqQhJqKdQhLq
PqN9QrK7R9PqL9DqR7K9QrN9PqR9K7RqN9QrP9K7RqPqN9QrR7K9RqPqN9QrS7K9
RqPqN9QrT7K9RqPqN9QrU7K9RqPqN9QrV7K9RqPqN9QrW7K9RqPqN9Qr
-----END RSA PRIVATE KEY-----
PEM;
    }

    /**
     * Generate a test ECDSA private key in PEM format.
     */
    private function generateTestEcdsaPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIIGLqHPFqcRbHkAqJlSWsE2Z6KJhVQa4iqF8j+YrF0sPoAoGCCqGSM49
AwEHoUQDQgAEPuXBPZWvNNQVmTRqNhPVLXMQKPqNQBZEJKMKQRVVGQTRVGZQPqNK
RQVGMQKPqNQBZEJKMKQRVVGQTRVGZQPqNKRQVGMQ==
-----END EC PRIVATE KEY-----
PEM;
    }
}
