<?php

declare(strict_types=1);

namespace App\Domain\TokenBlacklist\Model;

/**
 * OAuth2 Token Blacklist model.
 *
 * Represents a revoked JWT token identifier (jti) to prevent token reuse.
 * Blacklist entries prevent compromised or manually revoked tokens from being accepted.
 */
final readonly class OAuthTokenBlacklist
{
    /**
     * @param string $id Unique identifier
     * @param string $jti JWT ID (unique token identifier from JWT claims)
     * @param \DateTimeImmutable $expiresAt Original token expiration timestamp
     * @param \DateTimeImmutable $revokedAt Timestamp when token was revoked
     * @param string|null $reason Reason for revocation (optional)
     */
    public function __construct(
        public string $id,
        public string $jti,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $revokedAt,
        public ?string $reason = null,
    ) {
    }

    /**
     * Check if the blacklist entry has expired.
     *
     * Expired entries can be safely deleted as the original token would be expired anyway.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }
}
