<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Domain\Shared\Exception\RepositoryException;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepositoryInterface;
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
     * {@inheritDoc}
     */
    public function adminExists(): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*) as count')
            ->from(self::TABLE_NAME)
            ->where('roles::jsonb @> :adminRole')
            ->setParameter('adminRole', json_encode(['ROLE_ADMIN'], JSON_THROW_ON_ERROR));

        try {
            /** @var array{count: int}|false $result */
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return false;
            }

            return $result['count'] > 0;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to check if admin exists: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(int $page, int $itemsPerPage, string $orderBy = 'asc', string $sortField = 'email'): array
    {
        // Validate all inputs before any database operations
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than or equal to 1.');
        }

        if ($itemsPerPage < 1) {
            throw new \InvalidArgumentException('Items per page must be greater than or equal to 1.');
        }

        // Validate and normalize order direction
        $orderBy = strtolower($orderBy);
        if (!\in_array($orderBy, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Invalid order direction. Must be "asc" or "desc".');
        }

        // Validate sort field against whitelist (prevents SQL injection)
        $allowedFields = ['email', 'created_at', 'id'];
        if (!\in_array($sortField, $allowedFields, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid sort field "%s". Allowed fields: %s', $sortField, implode(', ', $allowedFields))
            );
        }

        try {
            /*
             * Performance Note: COUNT(*) Query Optimization
             *
             * The COUNT(*) query performs a full table scan on PostgreSQL for large tables.
             * This is acceptable for tables with < 100k rows but may become a bottleneck beyond that.
             *
             * Optimization options if performance degrades:
             * 1. Use PostgreSQL's pg_class.reltuples for approximate count:
             *    SELECT reltuples::bigint FROM pg_class WHERE relname = 'users'
             * 2. Implement caching for total count with short TTL (e.g., 5-10 minutes)
             * 3. Switch to cursor-based pagination (keyset pagination) for very large datasets
             * 4. Consider showing only "Next/Previous" without total count/pages
             *
             * Current decision: Use exact COUNT(*) for accuracy and simplicity.
             * Monitor performance and optimize if measured necessary (>100ms query time).
             */
            $countQueryBuilder = $this->connection->createQueryBuilder();
            $countQueryBuilder
                ->select('COUNT(*) as count')
                ->from(self::TABLE_NAME);

            /** @var array{count: int|numeric-string}|false $countResult */
            $countResult = $countQueryBuilder->executeQuery()->fetchAssociative();

            if (false === $countResult) {
                $total = 0;
            } else {
                $total = (int) $countResult['count'];
            }

            // Calculate offset and total pages
            $offset = ($page - 1) * $itemsPerPage;
            $totalPages = (int) ceil($total / $itemsPerPage);

            // Fetch users with pagination
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from(self::TABLE_NAME)
                ->orderBy($sortField, $orderBy)
                ->setMaxResults($itemsPerPage)
                ->setFirstResult($offset);

            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            // Hydrate users
            $users = array_map(fn(array $row): User => $this->hydrateUser($row), $results);

            return [
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'totalPages' => $totalPages,
            ];
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to paginate users: %s', $exception->getMessage()),
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
