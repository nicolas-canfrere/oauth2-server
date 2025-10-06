<?php

declare(strict_types=1);

namespace App\Domain\Security\Service;

/**
 * HMAC-SHA256 token hasher for OAuth2 tokens.
 *
 * Uses Symfony's APP_SECRET as the secret key for HMAC to prevent
 * rainbow table attacks on hashed tokens.
 *
 * Performance: ~0.1ms per hash (suitable for high-frequency operations)
 * Security: 256-bit hash with secret key (collision-resistant)
 */
final readonly class TokenHasher implements TokenHasherInterface
{
    private const ALGORITHM = 'sha256';
    private const MIN_SECRET_LENGTH = 32;

    /**
     * @param string $secret Secret key from APP_SECRET environment variable
     */
    public function __construct(
        private string $secret,
    ) {
        if (strlen($this->secret) < self::MIN_SECRET_LENGTH) {
            throw new \RuntimeException(
                sprintf(
                    'Token hasher secret must be at least %d characters. Configure APP_SECRET in .env file.',
                    self::MIN_SECRET_LENGTH
                )
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hash(string $token): string
    {
        if ('' === $token) {
            throw new \InvalidArgumentException('Cannot hash empty token.');
        }

        return hash_hmac(self::ALGORITHM, $token, $this->secret);
    }
}
