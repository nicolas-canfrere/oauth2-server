<?php

declare(strict_types=1);

namespace App\Domain\RefreshToken\Model;

/**
 * OAuth2 Refresh Token model.
 *
 * Represents a long-lived token used to obtain new access tokens without re-authentication.
 * Refresh tokens can be revoked and have automatic rotation support.
 */
final readonly class OAuthRefreshToken
{
    /**
     * @param string $id Unique identifier
     * @param string $token Refresh token value (encrypted in database)
     * @param string $clientId OAuth2 client identifier
     * @param string $userId User identifier
     * @param string[] $scopes Granted scopes
     * @param bool $isRevoked Whether the token has been revoked
     * @param \DateTimeImmutable $expiresAt Expiration timestamp
     * @param \DateTimeImmutable $createdAt Creation timestamp
     */
    public function __construct(
        public string $id,
        public string $token,
        public string $clientId,
        public string $userId,
        public array $scopes,
        public bool $isRevoked,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Check if the refresh token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    /**
     * Check if the refresh token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked;
    }
}
