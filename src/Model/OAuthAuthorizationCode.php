<?php

declare(strict_types=1);

namespace App\Model;

/**
 * OAuth2 Authorization Code model.
 *
 * Represents a temporary authorization code issued during the OAuth2 authorization code flow.
 * Codes are single-use and expire after a short period.
 */
final readonly class OAuthAuthorizationCode
{
    /**
     * @param string $id Unique identifier
     * @param string $code Authorization code value
     * @param string $clientId OAuth2 client identifier
     * @param string $userId User identifier
     * @param string $redirectUri Redirect URI used in authorization request
     * @param string[] $scopes Granted scopes
     * @param string|null $codeChallenge PKCE code challenge
     * @param string|null $codeChallengeMethod PKCE code challenge method (S256 or plain)
     * @param \DateTimeImmutable $expiresAt Expiration timestamp
     * @param \DateTimeImmutable $createdAt Creation timestamp
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $clientId,
        public string $userId,
        public string $redirectUri,
        public array $scopes,
        public ?string $codeChallenge,
        public ?string $codeChallengeMethod,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Check if the authorization code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }
}
