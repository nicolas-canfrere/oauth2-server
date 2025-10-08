<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Event subscriber that handles validation exceptions and converts them to OAuth2-compliant error responses.
 */
final readonly class ValidationExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * Subscribe to kernel exception events.
     *
     * @return array<string, array<int, string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 5],
        ];
    }

    /**
     * Handle validation exception and convert to OAuth2 error format.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Handle UnprocessableEntityHttpException which wraps ValidationFailedException
        if ($exception instanceof UnprocessableEntityHttpException) {
            $previous = $exception->getPrevious();
            if ($previous instanceof ValidationFailedException) {
                $this->handleValidationException($event, $previous);

                return;
            }
        }

        // Handle direct ValidationFailedException
        if ($exception instanceof ValidationFailedException) {
            $this->handleValidationException($event, $exception);
        }
    }

    private function handleValidationException(ExceptionEvent $event, ValidationFailedException $exception): void
    {
        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $errors[] = sprintf('%s: %s', $propertyPath, $violation->getMessage());
        }

        $errorDescription = [] !== $errors
            ? implode('; ', $errors)
            : 'Validation failed.';

        $response = new JsonResponse(
            [
                'error' => 'invalid_request',
                'error_description' => $errorDescription,
            ],
            Response::HTTP_BAD_REQUEST,
            ['Content-Type' => 'application/json']
        );

        $event->setResponse($response);
    }
}
