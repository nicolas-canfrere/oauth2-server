<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthKey;

/**
 * Interface for OAuth2 cryptographic key repository operations.
 *
 * Provides methods for managing cryptographic keys used for JWT signing
 * and rotation using Doctrine DBAL.
 */
interface KeyRepositoryInterface
{
    /**
     * Find all active keys ordered by creation date (newest first).
     *
     * @return list<OAuthKey> Array of active key objects
     */
    public function findActiveKeys(): array;

    /**
     * Find a key by its Key ID (kid).
     *
     * @param string $kid The key identifier
     *
     * @return OAuthKey|null Key object or null if not found
     */
    public function findByKid(string $kid): ?OAuthKey;

    /**
     * Save a cryptographic key (create or update).
     *
     * @param OAuthKey $key The key to save
     *
     * @throws \RuntimeException If save operation fails
     */
    public function save(OAuthKey $key): void;

    /**
     * Deactivate a key by its Key ID.
     *
     * @param string $kid The key identifier
     *
     * @return bool True if deactivation was successful, false otherwise
     */
    public function deactivate(string $kid): bool;

    /**
     * Delete all expired keys.
     *
     * @return int Number of deleted keys
     */
    public function deleteExpired(): int;

    /**
     * Find a key by its internal ID.
     *
     * @param string $id The ID of the key
     *
     * @return OAuthKey|null Key object or null if not found
     */
    public function find(string $id): ?OAuthKey;
}
