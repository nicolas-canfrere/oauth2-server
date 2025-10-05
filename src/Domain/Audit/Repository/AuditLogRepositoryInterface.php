<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Domain\Audit\Model\OAuthAuditLog;

/**
 * Interface for audit log repository operations.
 *
 * Provides methods for storing and querying security audit logs using Doctrine DBAL.
 */
interface AuditLogRepositoryInterface
{
    /**
     * Create a new audit log entry.
     *
     * @param OAuthAuditLog $auditLog The audit log to store
     *
     * @throws \RuntimeException If creation fails
     */
    public function create(OAuthAuditLog $auditLog): void;

    /**
     * Find an audit log by its ID.
     *
     * @param string $id The ID of the audit log
     *
     * @return OAuthAuditLog|null Audit log or null if not found
     */
    public function find(string $id): ?OAuthAuditLog;

    /**
     * Find all audit logs for a specific user.
     *
     * @param string $userId User ID to filter by
     * @param int    $limit  Maximum number of results
     * @param int    $offset Starting offset
     *
     * @return list<OAuthAuditLog> Array of audit logs
     */
    public function findByUserId(string $userId, int $limit = 100, int $offset = 0): array;

    /**
     * Find all audit logs for a specific client.
     *
     * @param string $clientId Client ID to filter by
     * @param int    $limit    Maximum number of results
     * @param int    $offset   Starting offset
     *
     * @return list<OAuthAuditLog> Array of audit logs
     */
    public function findByClientId(string $clientId, int $limit = 100, int $offset = 0): array;

    /**
     * Find audit logs by event type.
     *
     * @param AuditEventTypeEnum $eventType Event type to filter by
     * @param int                $limit     Maximum number of results
     * @param int                $offset    Starting offset
     *
     * @return list<OAuthAuditLog> Array of audit logs
     */
    public function findByEventType(AuditEventTypeEnum $eventType, int $limit = 100, int $offset = 0): array;

    /**
     * Find audit logs within a time range.
     *
     * @param \DateTimeImmutable $startDate Start of time range
     * @param \DateTimeImmutable $endDate   End of time range
     * @param int                $limit     Maximum number of results
     * @param int                $offset    Starting offset
     *
     * @return list<OAuthAuditLog> Array of audit logs
     */
    public function findByDateRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $limit = 1000,
        int $offset = 0,
    ): array;

    /**
     * Delete audit logs older than the specified retention period.
     *
     * @param \DateTimeImmutable $beforeDate Delete logs created before this date
     *
     * @return int Number of deleted records
     *
     * @throws \RuntimeException If deletion fails
     */
    public function deleteOlderThan(\DateTimeImmutable $beforeDate): int;

    /**
     * Count total audit logs (optionally filtered by user).
     *
     * @param string|null $userId Optional user ID to filter by
     *
     * @return int Total count of audit logs
     */
    public function count(?string $userId = null): int;
}
