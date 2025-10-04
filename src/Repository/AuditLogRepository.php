<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\AuditEventTypeEnum;
use App\Model\OAuthAuditLog;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * Repository for audit log management using Doctrine DBAL.
 *
 * Provides low-level database operations for security audit logs
 * using prepared statements for security and performance.
 */
final class AuditLogRepository implements AuditLogRepositoryInterface
{
    private const TABLE_NAME = 'oauth_audit_logs';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthAuditLog $auditLog): void
    {
        try {
            $this->connection->insert(
                self::TABLE_NAME,
                [
                    'id' => $auditLog->id,
                    'event_type' => $auditLog->eventType->value,
                    'level' => $auditLog->level,
                    'message' => $auditLog->message,
                    'context' => $auditLog->context,
                    'user_id' => $auditLog->userId,
                    'client_id' => $auditLog->clientId,
                    'ip_address' => $auditLog->ipAddress,
                    'user_agent' => $auditLog->userAgent,
                    'created_at' => $auditLog->createdAt,
                ],
                [
                    'id' => Types::STRING,
                    'event_type' => Types::STRING,
                    'level' => Types::STRING,
                    'message' => Types::TEXT,
                    'context' => Types::JSON,
                    'user_id' => Types::STRING,
                    'client_id' => Types::STRING,
                    'ip_address' => Types::STRING,
                    'user_agent' => Types::TEXT,
                    'created_at' => Types::DATETIME_IMMUTABLE,
                ]
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to create audit log: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?OAuthAuditLog
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

            return $this->hydrateAuditLog($result);
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch audit log with ID "%s": %s', $id, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserId(string $userId, int $limit = 100, int $offset = 0): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameter('user_id', $userId);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthAuditLog => $this->hydrateAuditLog($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch audit logs for user "%s": %s', $userId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(string $clientId, int $limit = 100, int $offset = 0): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('client_id = :client_id')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameter('client_id', $clientId);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthAuditLog => $this->hydrateAuditLog($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch audit logs for client "%s": %s', $clientId, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByEventType(AuditEventTypeEnum $eventType, int $limit = 100, int $offset = 0): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('event_type = :event_type')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameter('event_type', $eventType->value);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthAuditLog => $this->hydrateAuditLog($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch audit logs for event type "%s": %s', $eventType->value, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByDateRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $limit = 1000,
        int $offset = 0,
    ): array {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('created_at >= :start_date')
            ->andWhere('created_at <= :end_date')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->setParameter('start_date', $startDate, Types::DATETIME_IMMUTABLE)
            ->setParameter('end_date', $endDate, Types::DATETIME_IMMUTABLE);

        try {
            $results = $queryBuilder->executeQuery()->fetchAllAssociative();

            return array_map(
                fn(array $row): OAuthAuditLog => $this->hydrateAuditLog($row),
                $results
            );
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to fetch audit logs by date range: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteOlderThan(\DateTimeImmutable $beforeDate): int
    {
        try {
            $sql = 'DELETE FROM ' . self::TABLE_NAME . ' WHERE created_at < :before_date';
            $deletedCount = $this->connection->executeStatement(
                $sql,
                ['before_date' => $beforeDate],
                ['before_date' => Types::DATETIME_IMMUTABLE]
            );

            return (int) $deletedCount;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to delete old audit logs: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function count(?string $userId = null): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*) as total')->from(self::TABLE_NAME);

        if (null !== $userId) {
            $queryBuilder->where('user_id = :user_id')->setParameter('user_id', $userId);
        }

        try {
            /** @var int|numeric-string $result */
            $result = $queryBuilder->executeQuery()->fetchOne();

            return (int) $result;
        } catch (Exception $exception) {
            throw new RepositoryException(
                sprintf('Failed to count audit logs: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Hydrate database row into OAuthAuditLog model.
     *
     * @param array<string, mixed> $row Database row
     *
     * @throws \JsonException If JSON decoding fails
     */
    private function hydrateAuditLog(array $row): OAuthAuditLog
    {
        assert(is_string($row['context']));
        /** @var array<string, mixed> $context */
        $context = json_decode($row['context'], true, 512, JSON_THROW_ON_ERROR);

        assert(is_string($row['id']));
        assert(is_string($row['event_type']));
        assert(is_string($row['level']));
        assert(is_string($row['message']));
        assert(is_string($row['created_at']));

        return new OAuthAuditLog(
            id: $row['id'],
            eventType: AuditEventTypeEnum::from($row['event_type']),
            level: $row['level'],
            message: $row['message'],
            context: $context,
            userId: isset($row['user_id']) && is_string($row['user_id']) ? $row['user_id'] : null,
            clientId: isset($row['client_id']) && is_string($row['client_id']) ? $row['client_id'] : null,
            ipAddress: isset($row['ip_address']) && is_string($row['ip_address']) ? $row['ip_address'] : null,
            userAgent: isset($row['user_agent']) && is_string($row['user_agent']) ? $row['user_agent'] : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}
