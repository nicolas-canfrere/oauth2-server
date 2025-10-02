<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthClient;
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
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(OAuthClient $client): void
    {
        $exists = $this->find($client->id);

        if (null === $exists) {
            $this->insert($client);
        } else {
            $this->update($client);
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
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Insert a new client into the database.
     */
    private function insert(OAuthClient $client): void
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
            throw new \RuntimeException('Failed to create OAuth2 client: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Update an existing client in the database.
     */
    private function update(OAuthClient $client): void
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
            throw new \RuntimeException('Failed to update OAuth2 client: ' . $exception->getMessage(), 0, $exception);
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
