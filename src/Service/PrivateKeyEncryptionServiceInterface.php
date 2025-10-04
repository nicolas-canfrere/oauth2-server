<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for encrypting and decrypting OAuth2 private keys.
 *
 * Provides secure encryption/decryption of RSA/ECDSA private keys
 * before storing them in the database.
 */
interface PrivateKeyEncryptionServiceInterface
{
    /**
     * Encrypt a private key using AES-256-GCM.
     *
     * @param string $privateKey The private key in PEM format to encrypt
     *
     * @return string The encrypted private key (base64-encoded ciphertext with nonce and tag)
     *
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $privateKey): string;

    /**
     * Decrypt an encrypted private key.
     *
     * @param string $encryptedPrivateKey The encrypted private key (base64-encoded)
     *
     * @return string The decrypted private key in PEM format
     *
     * @throws \RuntimeException If decryption fails or authentication tag is invalid
     */
    public function decrypt(string $encryptedPrivateKey): string;
}
