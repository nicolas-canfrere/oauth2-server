<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\EventSubscriber;

use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Domain\OAuthClient\Exception\InvalidClientException;
use App\Infrastructure\Audit\EventSubscriber\OAuth2ExceptionSubscriber;
use App\OAuth2\Exception\AccessDeniedException;
use App\OAuth2\Exception\InvalidGrantException;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\InvalidScopeException;
use App\OAuth2\Exception\ServerErrorException;
use App\OAuth2\Exception\UnauthorizedClientException;
use App\OAuth2\Exception\UnsupportedGrantTypeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class OAuth2ExceptionSubscriberTest extends TestCase
{
    /** @var AuditLoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private AuditLoggerInterface $auditLogger;
    private OAuth2ExceptionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->subscriber = new OAuth2ExceptionSubscriber(
            $this->auditLogger,
            'https://docs.example.com/oauth2/errors'
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OAuth2ExceptionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        $this->assertSame(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
    }

    public function testOnKernelExceptionIgnoresNonOAuth2Exceptions(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $exception = new \RuntimeException('Generic exception');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $this->auditLogger
            ->expects($this->never())
            ->method('log');

        $this->subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('oauth2ExceptionProvider')]
    public function testOnKernelExceptionHandlesOAuth2Exceptions(
        string $exceptionClass,
        int $expectedHttpStatus,
        string $expectedError,
        string $expectedLogLevel,
    ): void {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/oauth/token', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Test Client',
        ]);

        /** @var \Throwable $exception */
        $exception = new $exceptionClass();

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $this->auditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) use ($expectedError, $expectedLogLevel): bool {
                $this->assertSame(AuditEventTypeEnum::OAUTH2_ERROR, $auditEvent->eventType);
                $this->assertSame($expectedLogLevel, $auditEvent->level);
                $this->assertStringContainsString($expectedError, $auditEvent->message);
                $this->assertSame('192.168.1.1', $auditEvent->ipAddress);
                $this->assertSame('Test Client', $auditEvent->userAgent);
                $this->assertArrayHasKey('error_code', $auditEvent->context);
                $this->assertArrayHasKey('http_status', $auditEvent->context);
                $this->assertSame($expectedError, $auditEvent->context['error_code']);

                return true;
            }));

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expectedHttpStatus, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertIsString($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('error_description', $responseData);
        $this->assertArrayHasKey('error_uri', $responseData);
        $this->assertSame($expectedError, $responseData['error']);
        $this->assertSame('https://docs.example.com/oauth2/errors/' . $expectedError, $responseData['error_uri']);
    }

    public function testOnKernelExceptionWithoutErrorUriBase(): void
    {
        $subscriberWithoutUri = new OAuth2ExceptionSubscriber($this->auditLogger, '');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/oauth/token');
        $exception = new InvalidRequestException();

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $this->auditLogger->expects($this->once())->method('log');

        $subscriberWithoutUri->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        $this->assertIsString($content);
        $responseData = json_decode($content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayNotHasKey('error_uri', $responseData);
    }

    /**
     * @return array<string, array{0: class-string, 1: int, 2: string, 3: string}>
     */
    public static function oauth2ExceptionProvider(): array
    {
        return [
            'InvalidRequestException' => [
                InvalidRequestException::class,
                400,
                'invalid_request',
                LogLevel::WARNING,
            ],
            'InvalidClientException' => [
                InvalidClientException::class,
                401,
                'invalid_client',
                LogLevel::WARNING,
            ],
            'InvalidGrantException' => [
                InvalidGrantException::class,
                400,
                'invalid_grant',
                LogLevel::WARNING,
            ],
            'UnauthorizedClientException' => [
                UnauthorizedClientException::class,
                403,
                'unauthorized_client',
                LogLevel::WARNING,
            ],
            'UnsupportedGrantTypeException' => [
                UnsupportedGrantTypeException::class,
                400,
                'unsupported_grant_type',
                LogLevel::WARNING,
            ],
            'InvalidScopeException' => [
                InvalidScopeException::class,
                400,
                'invalid_scope',
                LogLevel::WARNING,
            ],
            'AccessDeniedException' => [
                AccessDeniedException::class,
                403,
                'access_denied',
                LogLevel::WARNING,
            ],
            'ServerErrorException' => [
                ServerErrorException::class,
                500,
                'server_error',
                LogLevel::ERROR,
            ],
        ];
    }
}
