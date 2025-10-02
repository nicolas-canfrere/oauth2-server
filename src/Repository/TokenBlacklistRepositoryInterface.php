<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthTokenBlacklist;

/**
 * Repository interface for OAuth2 token blacklist management.
 *
 * Handles storage and verification of revoked JWT tokens to prevent reuse.
 */
interface TokenBlacklistRepositoryInterface
{
    /**
     * Add a JWT token to the blacklist.
     *
     * @param OAuthTokenBlacklist $blacklistEntry Blacklist entry to add
     *
     * @throws \RuntimeException If addition fails
     */
    public function add(OAuthTokenBlacklist $blacklistEntry): void;

    /**
     * Check if a JWT token is blacklisted.
     *
     * @param string $jti JWT ID (jti claim from token)
     *
     * @return bool True if token is blacklisted, false otherwise
     */
    public function isBlacklisted(string $jti): bool;

    /**
     * Delete all expired blacklist entries.
     *
     * Cleans up entries where the original token expiration has passed,
     * as these tokens would be rejected anyway due to expiration.
     *
     * @return int Number of deleted entries
     */
    public function deleteExpired(): int;
}
