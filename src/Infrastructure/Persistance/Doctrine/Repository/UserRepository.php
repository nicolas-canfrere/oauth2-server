<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Repository\RepositoryException;
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
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch user with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
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
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch user by email "%s": %s', $email, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(User $user): void
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
            throw new RepositoryException(
                sprintf('Failed to create user "%s": %s', $user->email, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(User $user): void
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
            throw new RepositoryException(
                sprintf('Failed to update user "%s": %s', $user->email, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
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
                throw new RepositoryException(sprintf('User with ID "%s" not found', $userId));
            }
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to update password for user ID "%s": %s', $userId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
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
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to delete user with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
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
