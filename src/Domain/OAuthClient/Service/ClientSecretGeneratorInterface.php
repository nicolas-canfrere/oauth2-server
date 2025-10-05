<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Service;

use App\Domain\OAuthClient\Exception\InvalidClientSecretException;

/**
 * Interface for generating and validating OAuth2 client secrets.
 *
 * Ensures client secrets meet security requirements per OAuth2 RFC 6749 Section 2.3.1:
 * - Sufficient entropy (≥128 bits recommended)
 * - Cryptographically secure random generation
 * - Resistance to brute-force attacks
 */
interface ClientSecretGeneratorInterface
{
    /**
     * Generate a cryptographically secure client secret.
     *
     * Uses random_bytes() to generate high-entropy secrets suitable for
     * OAuth2 confidential clients.
     *
     * @param int $length Byte length (default: 32 = 256-bit entropy)
     *
     * @return string Base64-encoded random secret (URL-safe)
     *
     * @throws \RuntimeException If secure random generation fails
     */
    public function generate(int $length = 32): string;

    /**
     * Validate client secret meets minimum security requirements.
     *
     * Enforces:
     * - Minimum length (32 characters for 256-bit entropy)
     * - No weak patterns (dictionary words, keyboard sequences, repetition)
     * - Sufficient entropy (Shannon entropy ≥3.5 bits/char)
     *
     * @param string $secret Plain-text secret to validate
     *
     * @throws InvalidClientSecretException If secret doesn't meet requirements
     */
    public function validate(string $secret): void;

    /**
     * Calculate Shannon entropy of a string.
     *
     * Measures randomness quality. Higher values indicate better entropy.
     * Minimum acceptable: 3.5 bits per character for secrets.
     *
     * @param string $value String to analyze
     *
     * @return float Entropy in bits per character
     */
    public function calculateEntropy(string $value): float;
}
