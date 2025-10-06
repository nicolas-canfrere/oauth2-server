<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Domain\Key\Model\OAuthKey;
use App\Domain\Key\Repository\KeyRepositoryInterface;
use App\Domain\Shared\Exception\RepositoryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for OAuth2 cryptographic key management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for cryptographic keys
 * used for JWT signing and key rotation.
 */
final class KeyRepository implements KeyRepositoryInterface
{
    private const TABLE_NAME = 'oauth_keys';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveKeys(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('is_active = :is_active')
            ->setParameter('is_active', true, Types::BOOLEAN)
            ->orderBy('created_at', 'DESC');

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthKey => $this->hydrateKey($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch active keys: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByKid(string $kid): ?OAuthKey
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('kid = :kid')
            ->setParameter('kid', $kid);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateKey($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch key by kid "%s": %s', $kid, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthKey $key): void
    {
        $insertData = [
            'id' => $key->id,
            'kid' => $key->kid,
            'algorithm' => $key->algorithm,
            'public_key' => $key->publicKey,
            'private_key_encrypted' => $key->privateKeyEncrypted,
            'is_active' => $key->isActive,
            'created_at' => $key->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $key->expiresAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_active' => Types::BOOLEAN,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData, $types);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create OAuth2 key (kid: %s): %s', $key->kid, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(OAuthKey $key): void
    {
        $updateData = [
            'kid' => $key->kid,
            'algorithm' => $key->algorithm,
            'public_key' => $key->publicKey,
            'private_key_encrypted' => $key->privateKeyEncrypted,
            'is_active' => $key->isActive,
            'expires_at' => $key->expiresAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_active' => Types::BOOLEAN,
        ];

        try {
            $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['id' => $key->id],
                $types
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to update OAuth2 key (kid: %s): %s', $key->kid, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(string $kid): bool
    {
        $updateData = [
            'is_active' => false,
        ];

        $types = [
            'is_active' => Types::BOOLEAN,
        ];

        try {
            $affectedRows = $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['kid' => $kid],
                $types
            );

            return $affectedRows > 0;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to deactivate key (kid: %s): %s', $kid, $exception->getMessage()),
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
                sprintf('Failed to delete expired keys: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?OAuthKey
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

            return $this->hydrateKey($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch key with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate OAuthKey from database row.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \Exception
     */
    private function hydrateKey(array $row): OAuthKey
    {
        return new OAuthKey(
            id: is_string($row['id']) ? $row['id'] : '',
            kid: is_string($row['kid']) ? $row['kid'] : '',
            algorithm: is_string($row['algorithm']) ? $row['algorithm'] : '',
            publicKey: is_string($row['public_key']) ? $row['public_key'] : '',
            privateKeyEncrypted: is_string($row['private_key_encrypted']) ? $row['private_key_encrypted'] : '',
            isActive: (bool) $row['is_active'],
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
            expiresAt: new \DateTimeImmutable(is_string($row['expires_at']) ? $row['expires_at'] : 'now'),
        );
    }
}
