<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Event subscriber for logging successful authentications.
 *
 * Listens to Symfony Security's LoginSuccessEvent and logs authentication
 * success to the audit trail with user details, IP address, and user agent.
 */
final readonly class AuthenticationSuccessSubscriber implements EventSubscriberInterface
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
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Handles successful login events and logs them to audit trail.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SecurityUser) {
            return;
        }

        $request = $event->getRequest();
        $ipAddress = $request->getClientIp() ?? 'unknown';
        $userAgent = $request->headers->get('User-Agent') ?? 'unknown';

        $auditEvent = AuditEventDTO::loginSuccess(
            userId: $user->getUserId(),
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            additionalContext: [
                'firewall' => $event->getFirewallName(),
                'authenticated_at' => date('c'),
            ]
        );

        $this->auditLogger->log($auditEvent);
    }
}
