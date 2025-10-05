<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\OAuth2\Exception\OAuth2Exception;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that handles OAuth2 exceptions and converts them to RFC 6749 compliant JSON responses.
 *
 * This subscriber intercepts OAuth2Exception instances thrown during request processing
 * and transforms them into properly formatted JSON error responses according to RFC 6749 Section 5.2.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final readonly class OAuth2ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private string $errorUriBase,
    ) {
    }

    /**
     * Subscribe to kernel exception events with high priority to handle OAuth2 exceptions early.
     *
     * @return array<string, array<int, string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    /**
     * Handle kernel exception events and convert OAuth2Exception to JSON response.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof OAuth2Exception) {
            return;
        }

        $request = $event->getRequest();
        $errorData = $exception->toArray();

        // Add error_uri if base URL is configured
        if ('' !== $this->errorUriBase) {
            $errorData['error_uri'] = $this->errorUriBase . '/' . $exception->getError();
        }

        // Create JSON response conforming to RFC 6749
        $response = new JsonResponse(
            $errorData,
            $exception->getHttpStatus(),
            ['Content-Type' => 'application/json']
        );

        $event->setResponse($response);

        // Log the OAuth2 error event
        $this->logOAuth2Error($exception, $request);
    }

    /**
     * Log OAuth2 error to audit trail with appropriate severity level.
     */
    private function logOAuth2Error(OAuth2Exception $exception, \Symfony\Component\HttpFoundation\Request $request): void
    {
        // Determine log level based on HTTP status (client errors = warning, server errors = error)
        $logLevel = $exception->getHttpStatus() >= 500 ? LogLevel::ERROR : LogLevel::WARNING;

        $auditEvent = new AuditEventDTO(
            eventType: AuditEventTypeEnum::OAUTH2_ERROR,
            level: $logLevel,
            message: sprintf(
                'OAuth2 error: %s - %s',
                $exception->getError(),
                $exception->getErrorDescription()
            ),
            context: [
                'error_code' => $exception->getError(),
                'error_description' => $exception->getErrorDescription(),
                'http_status' => $exception->getHttpStatus(),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
            ],
            userId: null,
            clientId: null,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        $this->auditLogger->log($auditEvent);
    }
}
