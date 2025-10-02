<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for secure token hashing.
 *
 * Used to hash OAuth2 tokens (refresh tokens, authorization codes) before
 * database storage using one-way cryptographic hashing.
 *
 * Security Note: This is NOT for passwords (use PasswordHasher instead).
 * Token hashing must be fast (<1ms) while password hashing should be slow.
 */
interface TokenHasherInterface
{
    /**
     * Hash a plaintext token using HMAC-SHA256.
     *
     * @param string $token Plaintext token to hash
     *
     * @return string 64-character hexadecimal hash
     *
     * @throws \RuntimeException If secret key is not configured or too weak
     * @throws \InvalidArgumentException If token is empty
     */
    public function hash(string $token): string;
}
