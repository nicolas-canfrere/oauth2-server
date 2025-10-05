<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Security\OAuth2ClientUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Event subscriber for OAuth2 client authentication audit logging.
 *
 * Listens to Symfony Security events and logs OAuth2 client authentication
 * attempts (success and failure) to the audit log.
 */
final readonly class OAuth2AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Log successful OAuth2 client authentication.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // Only log OAuth2 client authentication (not regular users)
        if (!$user instanceof OAuth2ClientUser) {
            return;
        }

        $request = $event->getRequest();
        $client = $user->getClient();

        $this->auditLogger->log(
            AuditEventDTO::clientAuthenticated(
                clientId: $client->clientId,
                ipAddress: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent'),
                additionalContext: [
                    'firewall' => $event->getFirewallName(),
                    'authentication_method' => $this->detectAuthenticationMethod($request),
                    'client_type' => $client->isConfidential ? 'confidential' : 'public',
                ]
            )
        );
    }

    /**
     * Log failed OAuth2 client authentication.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Only log failures on OAuth2 firewall
        if ('oauth' !== $event->getFirewallName()) {
            return;
        }

        $request = $event->getRequest();
        $exception = $event->getException();

        // Try to extract client_id from request
        $clientId = $this->extractClientIdFromRequest($request);

        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        $this->auditLogger->log(
            AuditEventDTO::clientAuthenticationFailed(
                clientId: $clientId,
                ipAddress: $ipAddress ?? 'unknown',
                userAgent: $userAgent ?? 'unknown',
                reason: $exception->getMessage(),
                additionalContext: [
                    'firewall' => $event->getFirewallName(),
                    'authentication_method' => $this->detectAuthenticationMethod($request),
                ]
            )
        );
    }

    /**
     * Detect which authentication method was used.
     */
    private function detectAuthenticationMethod(?Request $request): string
    {
        if (null === $request) {
            return 'unknown';
        }

        // Check for HTTP Basic Auth
        if ($request->headers->has('Authorization')) {
            $authHeader = $request->headers->get('Authorization');
            if (is_string($authHeader) && str_starts_with($authHeader, 'Basic ')) {
                return 'http_basic';
            }
        }

        // Check for POST body credentials
        if ($request->request->has('client_id') && $request->request->has('client_secret')) {
            return 'post_body';
        }

        // Public client (only client_id)
        if ($request->request->has('client_id')) {
            return 'public_client';
        }

        return 'unknown';
    }

    /**
     * Extract client_id from request (for failed authentication logging).
     */
    private function extractClientIdFromRequest(Request $request): ?string
    {
        // Try POST body
        $clientId = $request->request->get('client_id');
        if (is_string($clientId) && '' !== $clientId) {
            return $clientId;
        }

        // Try HTTP Basic Auth
        $authHeader = $request->headers->get('Authorization');
        if (is_string($authHeader) && str_starts_with($authHeader, 'Basic ')) {
            $encodedCredentials = substr($authHeader, 6);
            $decodedCredentials = base64_decode($encodedCredentials, true);

            if (false !== $decodedCredentials) {
                $parts = explode(':', $decodedCredentials, 2);
                if ('' !== $parts[0]) {
                    return $parts[0];
                }
            }
        }

        return null;
    }
}
