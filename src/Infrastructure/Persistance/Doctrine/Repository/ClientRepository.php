<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\OAuthClient\Repository\ClientRepositoryInterface;
use App\Domain\Shared\Exception\RepositoryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for OAuth2 client management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for OAuth2 clients
 * using prepared statements for security and performance.
 */
final class ClientRepository implements ClientRepositoryInterface
{
    private const TABLE_NAME = 'oauth_clients';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?OAuthClient
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

            return $this->hydrateClient($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch OAuth2 client with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(string $clientId): ?OAuthClient
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('client_id = :client_id')
            ->setParameter('client_id', $clientId);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateClient($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch OAuth2 client by client_id "%s": %s', $clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthClient $client): void
    {
        $insertData = [
            'id' => $client->id,
            'client_id' => $client->clientId,
            'client_secret_hash' => $client->clientSecretHash,
            'name' => $client->name,
            'redirect_uri' => $client->redirectUri,
            'grant_types' => json_encode($client->grantTypes),
            'scopes' => json_encode($client->scopes),
            'is_confidential' => $client->isConfidential,
            'pkce_required' => $client->pkceRequired,
            'created_at' => $client->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $client->createdAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_confidential' => Types::BOOLEAN,
            'pkce_required' => Types::BOOLEAN,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData, $types);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create OAuth2 client "%s" (ID: %s): %s', $client->clientId, $client->id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(OAuthClient $client): void
    {
        $updateData = [
            'client_id' => $client->clientId,
            'client_secret_hash' => $client->clientSecretHash,
            'name' => $client->name,
            'redirect_uri' => $client->redirectUri,
            'grant_types' => json_encode($client->grantTypes),
            'scopes' => json_encode($client->scopes),
            'is_confidential' => $client->isConfidential,
            'pkce_required' => $client->pkceRequired,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_confidential' => Types::BOOLEAN,
            'pkce_required' => Types::BOOLEAN,
        ];

        try {
            $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['id' => $client->id],
                $types
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to update OAuth2 client "%s" (ID: %s): %s', $client->clientId, $client->id, $exception->getMessage()),
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
                sprintf('Failed to delete OAuth2 client with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthClient => $this->hydrateClient($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch OAuth2 clients (limit: %d, offset: %d): %s', $limit, $offset, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(int $page, int $itemsPerPage, string $orderBy = 'asc', string $sortField = 'name'): array
    {
        // Validate all inputs before any database operations (prevents division by zero)
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
        $allowedFields = ['name', 'client_id', 'created_at', 'id'];
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
             *    SELECT reltuples::bigint FROM pg_class WHERE relname = 'oauth_clients'
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

            // Fetch clients with pagination
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from(self::TABLE_NAME)
                ->orderBy($sortField, $orderBy)
                ->setMaxResults($itemsPerPage)
                ->setFirstResult($offset);

            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            // Hydrate clients
            $clients = array_map(fn(array $row): OAuthClient => $this->hydrateClient($row), $results);

            return [
                'clients' => $clients,
                'total' => $total,
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'totalPages' => $totalPages,
            ];
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to paginate OAuth2 clients: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate OAuthClient from database row.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \Exception
     */
    private function hydrateClient(array $row): OAuthClient
    {
        $grantTypes = is_string($row['grant_types']) ? json_decode($row['grant_types'], true) : $row['grant_types'];
        $scopes = is_string($row['scopes']) ? json_decode($row['scopes'], true) : $row['scopes'];

        if (!is_array($grantTypes)) {
            $grantTypes = [];
        }

        if (!is_array($scopes)) {
            $scopes = [];
        }

        // Ensure arrays are lists of strings
        $grantTypes = array_values(array_filter($grantTypes, 'is_string'));
        $scopes = array_values(array_filter($scopes, 'is_string'));

        return new OAuthClient(
            id: is_string($row['id']) ? $row['id'] : '',
            clientId: is_string($row['client_id']) ? $row['client_id'] : '',
            clientSecretHash: is_string($row['client_secret_hash']) ? $row['client_secret_hash'] : '',
            name: is_string($row['name']) ? $row['name'] : '',
            redirectUri: is_string($row['redirect_uri']) ? $row['redirect_uri'] : '',
            grantTypes: $grantTypes,
            scopes: $scopes,
            isConfidential: (bool) $row['is_confidential'],
            pkceRequired: (bool) $row['pkce_required'],
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
            updatedAt: isset($row['updated_at']) && is_string($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
        );
    }
}
