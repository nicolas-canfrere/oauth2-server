<?php

declare(strict_types=1);

namespace App\Domain\Audit\Model;

use App\Domain\Audit\Enum\AuditEventTypeEnum;

/**
 * Audit log model for OAuth2 security events.
 *
 * Immutable value object representing a security audit log entry.
 * All security-relevant events (auth, token issuance, revocation) are stored here.
 */
final readonly class OAuthAuditLog
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public AuditEventTypeEnum $eventType,
        public string $level,
        public string $message,
        public array $context,
        public ?string $userId,
        public ?string $clientId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
