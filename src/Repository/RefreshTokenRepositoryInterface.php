<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthRefreshToken;

/**
 * Repository interface for OAuth2 refresh token management.
 *
 * Handles storage, retrieval, revocation and rotation of refresh tokens.
 */
interface RefreshTokenRepositoryInterface
{
    /**
     * Create a new refresh token.
     *
     * @param OAuthRefreshToken $refreshToken Refresh token to create
     *
     * @throws \RuntimeException If creation fails
     */
    public function create(OAuthRefreshToken $refreshToken): void;

    /**
     * Find a refresh token by its token value.
     *
     * @param string $token Refresh token value
     *
     * @return OAuthRefreshToken|null Refresh token if found, null otherwise
     */
    public function findByToken(string $token): ?OAuthRefreshToken;

    /**
     * Revoke a refresh token.
     *
     * @param string $token Refresh token value to revoke
     *
     * @return bool True if token was revoked, false if not found
     */
    public function revoke(string $token): bool;

    /**
     * Find all active (non-revoked, non-expired) refresh tokens for a user.
     *
     * @param string $userId User identifier
     *
     * @return OAuthRefreshToken[] List of active refresh tokens
     */
    public function findActiveByUser(string $userId): array;

    /**
     * Delete all expired refresh tokens.
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int;
}
