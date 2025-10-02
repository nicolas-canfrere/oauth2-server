<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthAuthorizationCode;

/**
 * Repository interface for OAuth2 authorization code management.
 *
 * Handles storage and retrieval of authorization codes used in the OAuth2 authorization code flow.
 */
interface AuthorizationCodeRepositoryInterface
{
    /**
     * Create a new authorization code.
     *
     * @param OAuthAuthorizationCode $authorizationCode Authorization code to create
     *
     * @throws \RuntimeException If creation fails
     */
    public function create(OAuthAuthorizationCode $authorizationCode): void;

    /**
     * Find an authorization code by its code value.
     *
     * @param string $code Authorization code value
     *
     * @return OAuthAuthorizationCode|null Authorization code if found, null otherwise
     */
    public function findByCode(string $code): ?OAuthAuthorizationCode;

    /**
     * Consume an authorization code (delete it after use).
     *
     * Authorization codes are single-use only. This method deletes the code
     * to prevent replay attacks.
     *
     * @param string $code Authorization code value
     *
     * @return bool True if code was consumed, false if not found
     */
    public function consume(string $code): bool;

    /**
     * Delete all expired authorization codes.
     *
     * @return int Number of deleted codes
     */
    public function deleteExpired(): int;
}
