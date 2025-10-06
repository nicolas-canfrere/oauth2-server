<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistance\Doctrine\Repository;

use App\Domain\TokenBlacklist\Model\OAuthTokenBlacklist;
use App\Domain\TokenBlacklist\Repository\TokenBlacklistRepositoryInterface;
use App\Repository\RepositoryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Repository for OAuth2 token blacklist management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for blacklisted JWT tokens
 * using prepared statements for security and performance. Optimized for fast lookups by jti.
 */
final class TokenBlacklistRepository implements TokenBlacklistRepositoryInterface
{
    private const TABLE_NAME = 'oauth_token_blacklist';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function add(OAuthTokenBlacklist $blacklistEntry): void
    {
        $insertData = [
            'id' => $blacklistEntry->id,
            'jti' => $blacklistEntry->jti,
            'expires_at' => $blacklistEntry->expiresAt->format('Y-m-d H:i:s'),
            'revoked_at' => $blacklistEntry->revokedAt->format('Y-m-d H:i:s'),
            'reason' => $blacklistEntry->reason,
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to add token to blacklist (jti: %s): %s', $blacklistEntry->jti, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isBlacklisted(string $jti): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from(self::TABLE_NAME)
            ->where('jti = :jti')
            ->setParameter('jti', $jti);

        try {
            $result = $queryBuilder->executeQuery()->fetchOne();
            $count = is_numeric($result) ? (int) $result : 0;

            return $count > 0;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to check if token is blacklisted (jti: %s): %s', $jti, $exception->getMessage()),
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
                sprintf('Failed to delete expired blacklist entries: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }
}
