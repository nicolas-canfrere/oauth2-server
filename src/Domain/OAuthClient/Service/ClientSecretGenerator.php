<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Service;

use App\Domain\OAuthClient\Exception\InvalidClientSecretException;

/**
 * Generates and validates cryptographically secure OAuth2 client secrets.
 *
 * Implements OAuth2 RFC 6749 Section 2.3.1 security requirements:
 * - Minimum 256-bit entropy (32 random bytes)
 * - Cryptographically secure random generation
 * - Pattern-based weakness detection
 * - Shannon entropy validation
 */
final readonly class ClientSecretGenerator implements ClientSecretGeneratorInterface
{
    private const MINIMUM_LENGTH = 32;
    private const MINIMUM_ENTROPY = 3.5;
    private const WEAK_PATTERNS = [
        '/(.)\1{3,}/',              // Repetitive characters (aaaa, 1111)
        '/^[a-z]+$/',               // Only lowercase letters
        '/^[A-Z]+$/',               // Only uppercase letters
        '/^[0-9]+$/',               // Only numbers
        '/^(password|secret|admin|test|demo|example)/i', // Common weak prefixes
        '/(qwerty|asdfgh|12345|abc123)/i', // Keyboard patterns
    ];

    /**
     * {@inheritDoc}
     */
    public function generate(int $length = 32): string
    {
        if ($length < self::MINIMUM_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Secret length must be at least %d bytes, %d given', self::MINIMUM_LENGTH, $length)
            );
        }

        try {
            $randomBytes = random_bytes($length);
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                sprintf('Failed to generate secure random bytes: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        // URL-safe Base64 encoding (RFC 4648)
        return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    /**
     * {@inheritDoc}
     */
    public function validate(string $secret): void
    {
        // Check minimum length
        if (mb_strlen($secret) < self::MINIMUM_LENGTH) {
            throw new InvalidClientSecretException(
                sprintf(
                    'Client secret must be at least %d characters long (256-bit entropy). Got %d characters.',
                    self::MINIMUM_LENGTH,
                    mb_strlen($secret)
                )
            );
        }

        // Check for weak patterns
        foreach (self::WEAK_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $secret)) {
                throw new InvalidClientSecretException(
                    'Client secret contains weak patterns. Use cryptographically secure random generation.'
                );
            }
        }

        // Check Shannon entropy
        $entropy = $this->calculateEntropy($secret);
        if ($entropy < self::MINIMUM_ENTROPY) {
            throw new InvalidClientSecretException(
                sprintf(
                    'Client secret entropy too low: %.2f bits/char (minimum: %.1f bits/char). Use random generation.',
                    $entropy,
                    self::MINIMUM_ENTROPY
                )
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function calculateEntropy(string $value): float
    {
        if ('' === $value) {
            return 0.0;
        }

        $length = mb_strlen($value);
        $frequencies = [];

        // Count character frequencies
        for ($i = 0; $i < $length; ++$i) {
            $char = mb_substr($value, $i, 1);
            $frequencies[$char] = ($frequencies[$char] ?? 0) + 1;
        }

        // Calculate Shannon entropy: H = -Σ(p(x) * log2(p(x)))
        $entropy = 0.0;
        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }
}
