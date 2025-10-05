<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Service\AuditLoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Event subscriber for logging failed authentication attempts.
 *
 * Listens to Symfony Security's LoginFailureEvent and logs authentication
 * failures to the audit trail with failure reason, IP address, and user agent.
 */
final readonly class AuthenticationFailureSubscriber implements EventSubscriberInterface
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
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Handles failed login events and logs them to audit trail.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ipAddress = $request->getClientIp() ?? 'unknown';
        $userAgent = $request->headers->get('User-Agent') ?? 'unknown';

        $exception = $event->getException();
        $reason = $exception->getMessage();

        // Extract attempted email from request payload if available
        $attemptedEmail = null;
        $content = $request->getContent();
        if ('' !== $content) {
            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
                if (isset($payload['email']) && \is_string($payload['email'])) {
                    $attemptedEmail = $payload['email'];
                }
            } catch (\JsonException) {
                // Ignore invalid JSON, audit log will not include attempted email
            }
        }

        $additionalContext = [
            'firewall' => $event->getFirewallName(),
            'exception_type' => $exception::class,
            'failed_at' => date('c'),
        ];

        if (null !== $attemptedEmail) {
            $additionalContext['attempted_email'] = $attemptedEmail;
        }

        $auditEvent = AuditEventDTO::loginFailure(
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            reason: $reason,
            additionalContext: $additionalContext
        );

        $this->auditLogger->log($auditEvent);
    }
}
