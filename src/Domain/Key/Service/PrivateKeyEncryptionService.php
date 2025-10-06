<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

/**
 * Service for encrypting and decrypting OAuth2 private keys using AES-256-GCM.
 *
 * This service provides authenticated encryption (AEAD) for private keys
 * before storing them in the database. AES-256-GCM ensures both confidentiality
 * and integrity of the encrypted data.
 *
 * Storage format: base64(nonce . ciphertext . tag)
 * - nonce: 12 bytes random initialization vector
 * - ciphertext: encrypted private key
 * - tag: 16 bytes authentication tag
 */
final readonly class PrivateKeyEncryptionService implements PrivateKeyEncryptionServiceInterface
{
    private const CIPHER_ALGO = 'aes-256-gcm';
    private const NONCE_LENGTH = 12; // 96 bits recommended for GCM
    private const TAG_LENGTH = 16; // 128 bits authentication tag

    /**
     * @param string $encryptionKey The master encryption key from environment (base64-encoded 32 bytes)
     */
    public function __construct(
        private string $encryptionKey,
    ) {
        $this->validateEncryptionKey();
    }

    public function encrypt(string $privateKey): string
    {
        // Generate random nonce (initialization vector)
        $nonce = random_bytes(self::NONCE_LENGTH);

        // Decode the base64-encoded master key
        $key = base64_decode($this->encryptionKey, true);
        if (false === $key) {
            throw new \RuntimeException('Failed to decode encryption key');
        }

        // Encrypt with AES-256-GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $privateKey,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if (false === $ciphertext) {
            throw new \RuntimeException('Failed to encrypt private key: ' . openssl_error_string());
        }

        // Combine nonce + ciphertext + tag and encode to base64
        return base64_encode($nonce . $ciphertext . $tag);
    }

    public function decrypt(string $encryptedPrivateKey): string
    {
        // Decode the base64-encoded encrypted data
        $data = base64_decode($encryptedPrivateKey, true);
        if (false === $data) {
            throw new \RuntimeException('Failed to decode encrypted private key');
        }

        // Extract nonce, ciphertext, and tag
        if (strlen($data) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Invalid encrypted data length');
        }

        $nonce = substr($data, 0, self::NONCE_LENGTH);
        $tag = substr($data, -self::TAG_LENGTH);
        $ciphertext = substr($data, self::NONCE_LENGTH, -self::TAG_LENGTH);

        // Decode the master key
        $key = base64_decode($this->encryptionKey, true);
        if (false === $key) {
            throw new \RuntimeException('Failed to decode encryption key');
        }

        // Decrypt with AES-256-GCM
        $privateKey = openssl_decrypt(
            $ciphertext,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if (false === $privateKey) {
            throw new \RuntimeException('Failed to decrypt private key: authentication tag verification failed or decryption error');
        }

        return $privateKey;
    }

    /**
     * Validate that the encryption key is properly formatted.
     *
     * @throws \RuntimeException If the key is invalid
     */
    private function validateEncryptionKey(): void
    {
        $key = base64_decode($this->encryptionKey, true);

        if (false === $key) {
            throw new \RuntimeException('PRIVATE_KEY_ENCRYPTION_KEY must be a valid base64-encoded string');
        }

        if (32 !== strlen($key)) {
            throw new \RuntimeException('PRIVATE_KEY_ENCRYPTION_KEY must be 32 bytes (256 bits) when decoded');
        }
    }
}
