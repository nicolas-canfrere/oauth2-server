<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\AccessToken\Event\AccessTokenIssuedEvent;
use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Domain\RefreshToken\Event\RefreshTokenIssuedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event subscriber for logging OAuth2 token events to audit trail.
 *
 * Listens to domain events related to access token and refresh token lifecycle
 * and logs them for security audit, compliance, and traceability purposes.
 */
final readonly class OAuth2TokenAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private RequestStack $requestStack,
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
            AccessTokenIssuedEvent::class => 'onAccessTokenIssued',
            RefreshTokenIssuedEvent::class => 'onRefreshTokenIssued',
        ];
    }

    /**
     * Handles access token issuance events and logs them to audit trail.
     */
    public function onAccessTokenIssued(AccessTokenIssuedEvent $event): void
    {
        $auditEvent = AuditEventDTO::accessTokenIssued(
            userId: $event->userId,
            clientId: $event->clientId,
            jti: $event->jti,
            scopes: $event->scopes,
            ipAddress: $this->getClientIp() ?? 'unknown',
            additionalContext: [
                'grant_type' => $event->grantType,
            ]
        );

        $this->auditLogger->log($auditEvent);
    }

    /**
     * Handles refresh token issuance events and logs them to audit trail.
     */
    public function onRefreshTokenIssued(RefreshTokenIssuedEvent $event): void
    {
        $auditEvent = AuditEventDTO::refreshTokenIssued(
            userId: $event->userId,
            clientId: $event->clientId,
            tokenIdentifier: $event->tokenId,
            scopes: $event->scopes,
            ipAddress: $this->getClientIp() ?? 'unknown'
        );

        $this->auditLogger->log($auditEvent);
    }

    /**
     * Get client IP address from request.
     *
     * Returns null if not in a request context (e.g., CLI commands, background jobs).
     */
    private function getClientIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return $request->getClientIp();
    }
}
