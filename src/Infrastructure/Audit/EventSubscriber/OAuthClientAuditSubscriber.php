<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Domain\OAuthClient\Event\OAuthClientCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for logging OAuth client management events to audit trail.
 *
 * Listens to domain events related to OAuth client lifecycle and logs them
 * for security audit, compliance, and traceability purposes.
 */
final readonly class OAuthClientAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            OAuthClientCreatedEvent::class => 'onClientCreated',
        ];
    }

    /**
     * Handles OAuth client creation events and logs them to audit trail.
     */
    public function onClientCreated(OAuthClientCreatedEvent $event): void
    {
        $auditEvent = AuditEventDTO::clientCreated(
            clientId: $event->clientId,
            clientName: $event->clientName,
            additionalContext: [
                'redirect_uri' => $event->redirectUri,
                'grant_types' => $event->grantTypes,
                'scopes' => $event->scopes,
                'is_confidential' => $event->isConfidential,
                'pkce_required' => $event->pkceRequired,
            ]
        );

        $this->auditLogger->log($auditEvent);
    }
}
