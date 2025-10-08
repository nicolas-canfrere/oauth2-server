<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that handles business exceptions and converts them to OAuth2-compliant error responses.
 *
 * This subscriber catches business logic exceptions (InvalidArgumentException, RuntimeException, etc.)
 * and transforms them into properly formatted JSON error responses following OAuth2 standards.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Subscribe to kernel exception events with lower priority than validation subscriber.
     *
     * @return array<string, array<int, string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    /**
     * Handle business exceptions and convert to OAuth2 error format.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle exceptions from API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Handle InvalidArgumentException (client errors)
        if ($exception instanceof \InvalidArgumentException) {
            $response = new JsonResponse(
                [
                    'error' => 'invalid_request',
                    'error_description' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST,
                ['Content-Type' => 'application/json']
            );

            $event->setResponse($response);

            return;
        }

        // Handle RuntimeException and other uncaught exceptions (server errors)
        if ($exception instanceof \RuntimeException) {
            // Log the exception for debugging
            $this->logger->error('Unexpected error in API request', [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]);

            $response = new JsonResponse(
                [
                    'error' => 'server_error',
                    'error_description' => 'An unexpected error occurred.',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'application/json']
            );

            $event->setResponse($response);
        }
    }
}
