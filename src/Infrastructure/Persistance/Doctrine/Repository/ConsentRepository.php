<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Model\UserConsent;
use App\Repository\ConsentRepositoryInterface;
use App\Repository\RepositoryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Repository for user consent management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for user consents
 * using prepared statements for security and performance.
 */
final class ConsentRepository implements ConsentRepositoryInterface
{
    private const TABLE_NAME = 'user_consents';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findConsent(string $userId, string $clientId): ?UserConsent
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->andWhere('client_id = :client_id')
            ->setParameter('user_id', $userId)
            ->setParameter('client_id', $clientId);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateConsent($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch consent for user "%s" and client "%s": %s', $userId, $clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function saveConsent(UserConsent $consent): void
    {
        $exists = $this->findConsent($consent->userId, $consent->clientId);

        if (null === $exists) {
            $this->insert($consent);
        } else {
            $this->update($consent);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function revokeConsent(string $userId, string $clientId): bool
    {
        try {
            $affectedRows = $this->connection->delete(
                self::TABLE_NAME,
                [
                    'user_id' => $userId,
                    'client_id' => $clientId,
                ]
            );

            return $affectedRows > 0;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to revoke consent for user "%s" and client "%s": %s', $userId, $clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteExpired(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where('expires_at < :now')
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        try {
            return $queryBuilder->executeStatement();
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to delete expired consents: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByUser(string $userId): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->orderBy('granted_at', 'DESC')
            ->setParameter('user_id', $userId);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): UserConsent => $this->hydrateConsent($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch consents for user "%s": %s', $userId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Insert a new consent into the database.
     */
    private function insert(UserConsent $consent): void
    {
        $insertData = [
            'id' => $consent->id,
            'user_id' => $consent->userId,
            'client_id' => $consent->clientId,
            'scopes' => json_encode($consent->scopes),
            'granted_at' => $consent->grantedAt->format('Y-m-d H:i:s'),
            'expires_at' => $consent->expiresAt->format('Y-m-d H:i:s'),
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create user consent for user "%s" and client "%s": %s', $consent->userId, $consent->clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Update an existing consent in the database.
     */
    private function update(UserConsent $consent): void
    {
        $updateData = [
            'scopes' => json_encode($consent->scopes),
            'granted_at' => $consent->grantedAt->format('Y-m-d H:i:s'),
            'expires_at' => $consent->expiresAt->format('Y-m-d H:i:s'),
        ];

        try {
            $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                [
                    'user_id' => $consent->userId,
                    'client_id' => $consent->clientId,
                ]
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to update user consent for user "%s" and client "%s": %s', $consent->userId, $consent->clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate UserConsent from database row.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \Exception
     */
    private function hydrateConsent(array $row): UserConsent
    {
        $scopes = is_string($row['scopes']) ? json_decode($row['scopes'], true) : $row['scopes'];

        if (!is_array($scopes)) {
            $scopes = [];
        }

        // Ensure array is a list of strings
        $scopes = array_values(array_filter($scopes, 'is_string'));

        return new UserConsent(
            id: is_string($row['id']) ? $row['id'] : '',
            userId: is_string($row['user_id']) ? $row['user_id'] : '',
            clientId: is_string($row['client_id']) ? $row['client_id'] : '',
            scopes: $scopes,
            grantedAt: new \DateTimeImmutable(is_string($row['granted_at']) ? $row['granted_at'] : 'now'),
            expiresAt: new \DateTimeImmutable(is_string($row['expires_at']) ? $row['expires_at'] : 'now'),
        );
    }
}
