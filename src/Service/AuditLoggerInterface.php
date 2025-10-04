<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AuditEventDTO;

/**
 * Interface for audit logging service.
 *
 * Provides dual backend logging: JSON Lines files (via Monolog) + Database storage.
 * All security-relevant events MUST be logged through this interface.
 */
interface AuditLoggerInterface
{
    /**
     * Log a security audit event.
     *
     * Events are logged to both:
     * - JSON Lines file via Monolog (for log aggregation systems)
     * - Database table (for querying and retention management)
     *
     * @param AuditEventDTO $event The audit event to log
     *
     * @throws \RuntimeException If logging fails
     */
    public function log(AuditEventDTO $event): void;

    /**
     * Log multiple audit events in batch.
     *
     * More efficient than individual log() calls for bulk operations.
     *
     * @param list<AuditEventDTO> $events Array of audit events
     *
     * @throws \RuntimeException If batch logging fails
     */
    public function logBatch(array $events): void;
}
