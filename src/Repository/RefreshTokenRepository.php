<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthRefreshToken;
use App\Service\TokenHasherInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for OAuth2 refresh token management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for OAuth2 refresh tokens
 * using prepared statements for security and performance.
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private const TABLE_NAME = 'oauth_refresh_tokens';

    public function __construct(
        private readonly Connection $connection,
        private readonly TokenHasherInterface $tokenHasher,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthRefreshToken $refreshToken): void
    {
        $insertData = [
            'id' => $refreshToken->id,
            'token_hash' => $this->tokenHasher->hash($refreshToken->token),
            'client_id' => $refreshToken->clientId,
            'user_id' => $refreshToken->userId,
            'scopes' => json_encode($refreshToken->scopes),
            'is_revoked' => $refreshToken->isRevoked,
            'expires_at' => $refreshToken->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $refreshToken->createdAt->format('Y-m-d H:i:s'),
        ];

        $types = [
            'is_revoked' => Types::BOOLEAN,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData, $types);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create refresh token for client "%s": %s', $refreshToken->clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByToken(string $token): ?OAuthRefreshToken
    {
        $tokenHash = $this->tokenHasher->hash($token);

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('token_hash = :token_hash')
            ->setParameter('token_hash', $tokenHash);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateRefreshToken($result, $token);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch refresh token: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function revoke(string $token): bool
    {
        $tokenHash = $this->tokenHasher->hash($token);

        $updateData = [
            'is_revoked' => true,
        ];

        $types = [
            'is_revoked' => Types::BOOLEAN,
        ];

        try {
            $affectedRows = $this->connection->update(
                self::TABLE_NAME,
                $updateData,
                ['token_hash' => $tokenHash],
                $types
            );

            return $affectedRows > 0;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to revoke refresh token: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveByUser(string $userId): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->andWhere('is_revoked = :is_revoked')
            ->andWhere('expires_at > :now')
            ->setParameter('user_id', $userId)
            ->setParameter('is_revoked', false, Types::BOOLEAN)
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC');

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthRefreshToken => $this->hydrateRefreshToken($row, '***REDACTED***'),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch active refresh tokens for user "%s": %s', $userId, $exception->getMessage()),
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
                sprintf('Failed to delete expired refresh tokens: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate OAuthRefreshToken from database row.
     *
     * @param array<string, mixed> $row Database row
     * @param string $plaintextToken Original plaintext token (not stored in DB)
     *
     * @throws \Exception
     */
    private function hydrateRefreshToken(array $row, string $plaintextToken): OAuthRefreshToken
    {
        $scopes = is_string($row['scopes']) ? json_decode($row['scopes'], true) : $row['scopes'];

        if (!is_array($scopes)) {
            $scopes = [];
        }

        // Ensure array is a list of strings
        $scopes = array_values(array_filter($scopes, 'is_string'));

        return new OAuthRefreshToken(
            id: is_string($row['id']) ? $row['id'] : '',
            token: $plaintextToken,
            clientId: is_string($row['client_id']) ? $row['client_id'] : '',
            userId: is_string($row['user_id']) ? $row['user_id'] : '',
            scopes: $scopes,
            isRevoked: (bool) $row['is_revoked'],
            expiresAt: new \DateTimeImmutable(is_string($row['expires_at']) ? $row['expires_at'] : 'now'),
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
        );
    }
}
