<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Model\User;

/**
 * Interface for user repository operations.
 *
 * Provides methods for managing users using Doctrine DBAL.
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by their internal ID.
     *
     * @param string $id The ID of the user
     *
     * @return User|null User object or null if not found
     */
    public function find(string $id): ?User;

    /**
     * Find a user by their email address.
     *
     * @param string $email The email address
     *
     * @return User|null User object or null if not found
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new user.
     *
     * @param User $user The user to create
     *
     * @throws \RuntimeException If user already exists or creation fails
     */
    public function create(User $user): void;

    /**
     * Update an existing user.
     *
     * @param User $user The user to update
     *
     * @throws \RuntimeException If update operation fails
     */
    public function update(User $user): void;

    /**
     * Update user password.
     *
     * @param string $userId       The user ID
     * @param string $passwordHash The new password hash (bcrypt)
     *
     * @throws \RuntimeException If update operation fails
     */
    public function updatePassword(string $userId, string $passwordHash): void;

    /**
     * Delete a user.
     *
     * @param string $id The ID of the user to delete
     *
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(string $id): bool;
}
