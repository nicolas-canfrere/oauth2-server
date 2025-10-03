<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for user management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for users
 * using prepared statements for security and performance.
 */
final class UserRepository implements UserRepositoryInterface
{
    private const TABLE_NAME = 'users';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?User
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('id = :id')
            ->setParameter('id', $id);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateUser($result);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?User
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('email = :email')
            ->setParameter('email', $email);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateUser($result);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(User $user): void
    {
        $exists = $this->find($user->id);

        if (null === $exists) {
            $this->insert($user);
        } else {
            $this->update($user);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updatePassword(string $userId, string $passwordHash): void
    {
        $updateData = [
            'password_hash' => $passwordHash,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        try {
            $affectedRows = $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['id' => $userId]
            );

            if (0 === $affectedRows) {
                throw new \RuntimeException("User with ID {$userId} not found");
            }
        } catch (Exception $exception) {
            throw new \RuntimeException('Failed to update user password: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $id): bool
    {
        try {
            $affectedRows = $this->connection->delete(
                self::TABLE_NAME,
                ['id' => $id]
            );

            return $affectedRows > 0;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Insert a new user into the database.
     */
    private function insert(User $user): void
    {
        $insertData = [
            'id' => $user->id,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'is_2fa_enabled' => $user->is2faEnabled,
            'totp_secret' => $user->totpSecret,
            'roles' => $user->roles,
            'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $user->createdAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_2fa_enabled' => Types::BOOLEAN,
            'roles' => Types::JSON,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData, $types);
        } catch (Exception $exception) {
            throw new \RuntimeException('Failed to create user: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Update an existing user in the database.
     */
    private function update(User $user): void
    {
        $updateData = [
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'is_2fa_enabled' => $user->is2faEnabled,
            'totp_secret' => $user->totpSecret,
            'roles' => $user->roles,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_2fa_enabled' => Types::BOOLEAN,
            'roles' => Types::JSON,
        ];

        try {
            $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['id' => $user->id],
                $types
            );
        } catch (Exception $exception) {
            throw new \RuntimeException('Failed to update user: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Hydrate User from database row.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \Exception
     */
    private function hydrateUser(array $row): User
    {
        $rolesJson = $row['roles'] ?? '[]';
        if (!\is_string($rolesJson)) {
            throw new \RuntimeException('roles column must be a string');
        }

        $roles = json_decode($rolesJson, true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($roles)) {
            throw new \RuntimeException('roles must be a JSON array');
        }

        /** @var array<string> $rolesTyped */
        $rolesTyped = array_filter(array_map(static function (mixed $role): ?string {
            if (\is_string($role)) {
                return $role;
            }

            return null;
        }, $roles));

        return new User(
            id: is_string($row['id']) ? $row['id'] : '',
            email: is_string($row['email']) ? $row['email'] : '',
            passwordHash: is_string($row['password_hash']) ? $row['password_hash'] : '',
            is2faEnabled: (bool) $row['is_2fa_enabled'],
            totpSecret: isset($row['totp_secret']) && is_string($row['totp_secret']) ? $row['totp_secret'] : null,
            roles: $rolesTyped,
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
            updatedAt: isset($row['updated_at']) && is_string($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
        );
    }
}
