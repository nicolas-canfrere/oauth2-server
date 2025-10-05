<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Model\OAuthAuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Audit\Service\AuditLoggerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;

/**
 * Audit logging service with dual backend storage.
 *
 * Logs security events to:
 * 1. JSON Lines files via Monolog (audit channel)
 * 2. PostgreSQL database for querying and retention management
 *
 * This ensures both real-time log streaming (for SIEM/ELK) and
 * structured querying capabilities for security analysis.
 */
final class AuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $auditLogger,
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function log(AuditEventDTO $event): void
    {
        // 1. Log to file via Monolog (JSON Lines format)
        $this->logToFile($event);

        // 2. Store in database for querying
        $this->logToDatabase($event);
    }

    /**
     * {@inheritDoc}
     */
    public function logBatch(array $events): void
    {
        foreach ($events as $event) {
            $this->log($event);
        }
    }

    /**
     * Log event to file via Monolog.
     *
     * Monolog will format this as JSON Lines and handle rotation automatically.
     */
    private function logToFile(AuditEventDTO $event): void
    {
        $logLevel = $this->mapLogLevel($event->level);
        $context = $this->buildLogContext($event);

        $this->auditLogger->log($logLevel, $event->message, $context);
    }

    /**
     * Store event in database.
     *
     * @throws \RuntimeException If database storage fails
     */
    private function logToDatabase(AuditEventDTO $event): void
    {
        /** @var array<string, mixed> $context */
        $context = $event->context;

        $auditLog = new OAuthAuditLog(
            id: Uuid::v4()->toString(),
            eventType: $event->eventType,
            level: $event->level,
            message: $event->message,
            context: $context,
            userId: $event->userId,
            clientId: $event->clientId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            createdAt: new \DateTimeImmutable(),
        );

        $this->auditLogRepository->create($auditLog);
    }

    /**
     * Build structured log context from event DTO.
     *
     * @return array<string, mixed>
     */
    private function buildLogContext(AuditEventDTO $event): array
    {
        return [
            'event_type' => $event->eventType->value,
            'user_id' => $event->userId,
            'client_id' => $event->clientId,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'context' => $event->context,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    /**
     * Map string log level to PSR-3 LogLevel constant.
     */
    private function mapLogLevel(string $level): string
    {
        return match (strtolower($level)) {
            'emergency' => LogLevel::EMERGENCY,
            'alert' => LogLevel::ALERT,
            'critical' => LogLevel::CRITICAL,
            'error' => LogLevel::ERROR,
            'warning' => LogLevel::WARNING,
            'notice' => LogLevel::NOTICE,
            'info' => LogLevel::INFO,
            'debug' => LogLevel::DEBUG,
            default => LogLevel::INFO,
        };
    }
}
