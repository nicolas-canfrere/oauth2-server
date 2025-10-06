<?php

declare(strict_types=1);

namespace App\Domain\Consent\Repository;

use App\Domain\Consent\Model\UserConsent;

/**
 * Interface for user consent repository operations.
 *
 * Provides methods for managing user consents using Doctrine DBAL.
 */
interface ConsentRepositoryInterface
{
    /**
     * Find consent for a specific user and client.
     *
     * @param string $userId   The user ID
     * @param string $clientId The client ID
     *
     * @return UserConsent|null Consent object or null if not found
     */
    public function findConsent(string $userId, string $clientId): ?UserConsent;

    /**
     * Save a user consent (create or update).
     *
     * @param UserConsent $consent The consent to save
     *
     * @throws \RuntimeException If save operation fails
     */
    public function saveConsent(UserConsent $consent): void;

    /**
     * Revoke consent for a specific user and client.
     *
     * @param string $userId   The user ID
     * @param string $clientId The client ID
     *
     * @return bool True if revocation was successful, false otherwise
     */
    public function revokeConsent(string $userId, string $clientId): bool;

    /**
     * Delete all expired consents.
     *
     * @return int Number of deleted consents
     */
    public function deleteExpired(): int;

    /**
     * Find all consents for a specific user.
     *
     * @param string $userId The user ID
     *
     * @return list<UserConsent> Array of consent objects
     */
    public function findByUser(string $userId): array;
}
