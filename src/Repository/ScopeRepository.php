<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthScope;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for OAuth2 scope management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for OAuth2 scopes
 * using prepared statements for security and performance.
 */
final class ScopeRepository implements ScopeRepositoryInterface
{
    private const TABLE_NAME = 'oauth_scopes';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('scope', 'ASC');

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthScope => $this->hydrateScope($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch all scopes: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByScopes(array $scopes): array
    {
        if ([] === $scopes) {
            return [];
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('scope IN (:scopes)')
            ->setParameter('scopes', $scopes, Connection::PARAM_STR_ARRAY);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthScope => $this->hydrateScope($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch scopes: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaults(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('is_default = :is_default')
            ->setParameter('is_default', true, Types::BOOLEAN)
            ->orderBy('scope', 'ASC');

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthScope => $this->hydrateScope($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch default scopes: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthScope $scope): void
    {
        $insertData = [
            'id' => $scope->id,
            'scope' => $scope->scope,
            'description' => $scope->description,
            'is_default' => $scope->isDefault,
            'created_at' => $scope->createdAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_default' => Types::BOOLEAN,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData, $types);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create OAuth2 scope "%s": %s', $scope->scope, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(OAuthScope $scope): void
    {
        $updateData = [
            'scope' => $scope->scope,
            'description' => $scope->description,
            'is_default' => $scope->isDefault,
        ];

        $types = [
            'is_default' => Types::BOOLEAN,
        ];

        try {
            $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['id' => $scope->id],
                $types
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to update OAuth2 scope "%s": %s', $scope->scope, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?OAuthScope
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

            return $this->hydrateScope($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch scope with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate OAuthScope from database row.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \Exception
     */
    private function hydrateScope(array $row): OAuthScope
    {
        return new OAuthScope(
            id: is_string($row['id']) ? $row['id'] : '',
            scope: is_string($row['scope']) ? $row['scope'] : '',
            description: is_string($row['description']) ? $row['description'] : '',
            isDefault: (bool) $row['is_default'],
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
        );
    }
}
