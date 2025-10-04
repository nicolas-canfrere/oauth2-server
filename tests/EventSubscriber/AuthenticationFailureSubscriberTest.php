<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\DTO\AuditEventDTO;
use App\Enum\AuditEventTypeEnum;
use App\EventSubscriber\AuthenticationFailureSubscriber;
use App\Service\AuditLoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Unit tests for AuthenticationFailureSubscriber.
 *
 * Validates that failed authentication attempts are properly logged to audit trail.
 */
final class AuthenticationFailureSubscriberTest extends TestCase
{
    private AuditLoggerInterface&MockObject $mockAuditLogger;
    private AuthenticationFailureSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mockAuditLogger = $this->createMock(AuditLoggerInterface::class);

        $this->subscriber = new AuthenticationFailureSubscriber(
            $this->mockAuditLogger
        );
    }

    public function testSubscribesToLoginFailureEvent(): void
    {
        $subscribedEvents = AuthenticationFailureSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginFailureEvent::class, $subscribedEvents);
        $this->assertSame('onLoginFailure', $subscribedEvents[LoginFailureEvent::class]);
    }

    public function testOnLoginFailureLogsAuditEventWithBadCredentials(): void
    {
        $requestBody = json_encode([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            uri: '/admin/login',
            method: 'POST',
            server: [
                'REMOTE_ADDR' => '10.0.0.50',
                'HTTP_USER_AGENT' => 'curl/7.68.0',
            ],
            content: $requestBody
        );
        $request->headers->set('Content-Type', 'application/json');

        $exception = new BadCredentialsException('Invalid credentials.');

        $passport = new Passport(
            new UserBadge('test@example.com'),
            new PasswordCredentials('wrong-password')
        );

        $event = new LoginFailureEvent(
            exception: $exception,
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin',
            passport: $passport
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertSame(AuditEventTypeEnum::LOGIN_FAILURE, $auditEvent->eventType);
                $this->assertSame('warning', $auditEvent->level);
                $this->assertStringContainsString('Invalid credentials', $auditEvent->message);
                $this->assertNull($auditEvent->userId);
                $this->assertSame('10.0.0.50', $auditEvent->ipAddress);
                $this->assertSame('curl/7.68.0', $auditEvent->userAgent);
                $this->assertArrayHasKey('firewall', $auditEvent->context);
                $this->assertSame('admin', $auditEvent->context['firewall']);
                $this->assertArrayHasKey('exception_type', $auditEvent->context);
                $this->assertSame(BadCredentialsException::class, $auditEvent->context['exception_type']);
                $this->assertArrayHasKey('attempted_email', $auditEvent->context);
                $this->assertSame('test@example.com', $auditEvent->context['attempted_email']);
                $this->assertArrayHasKey('failed_at', $auditEvent->context);

                return true;
            }));

        $this->subscriber->onLoginFailure($event);
    }

    public function testOnLoginFailureLogsAuditEventWithUserNotFound(): void
    {
        $requestBody = json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'some-password',
        ], \JSON_THROW_ON_ERROR);

        $request = Request::create(
            uri: '/admin/login',
            method: 'POST',
            server: [
                'REMOTE_ADDR' => '192.168.1.200',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
            ],
            content: $requestBody
        );
        $request->headers->set('Content-Type', 'application/json');

        $exception = new UserNotFoundException('User not found.');

        $passport = new Passport(
            new UserBadge('nonexistent@example.com'),
            new PasswordCredentials('some-password')
        );

        $event = new LoginFailureEvent(
            exception: $exception,
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin',
            passport: $passport
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertSame(AuditEventTypeEnum::LOGIN_FAILURE, $auditEvent->eventType);
                $this->assertStringContainsString('User not found', $auditEvent->message);
                $this->assertSame(UserNotFoundException::class, $auditEvent->context['exception_type']);
                $this->assertSame('nonexistent@example.com', $auditEvent->context['attempted_email']);

                return true;
            }));

        $this->subscriber->onLoginFailure($event);
    }

    public function testOnLoginFailureWithoutAttemptedEmailInRequest(): void
    {
        $request = Request::create(
            uri: '/admin/login',
            method: 'POST',
            server: [
                'REMOTE_ADDR' => '172.16.0.1',
                'HTTP_USER_AGENT' => 'Test Agent',
            ],
            content: ''
        );

        $exception = new AuthenticationException('Authentication failed.');

        $passport = new Passport(
            new UserBadge('test@example.com'),
            new PasswordCredentials('password')
        );

        $event = new LoginFailureEvent(
            exception: $exception,
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            request: $request,
            response: null,
            firewallName: 'main',
            passport: $passport
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertArrayNotHasKey('attempted_email', $auditEvent->context);

                return true;
            }));

        $this->subscriber->onLoginFailure($event);
    }

    public function testOnLoginFailureWithInvalidJsonInRequest(): void
    {
        $request = Request::create(
            uri: '/admin/login',
            method: 'POST',
            server: [
                'REMOTE_ADDR' => '203.0.113.50',
                'HTTP_USER_AGENT' => 'Browser',
            ],
            content: 'invalid-json-content'
        );

        $exception = new BadCredentialsException('Invalid credentials.');

        $passport = new Passport(
            new UserBadge('test@example.com'),
            new PasswordCredentials('password')
        );

        $event = new LoginFailureEvent(
            exception: $exception,
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin',
            passport: $passport
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                // Should not have attempted_email due to invalid JSON
                $this->assertArrayNotHasKey('attempted_email', $auditEvent->context);

                return true;
            }));

        $this->subscriber->onLoginFailure($event);
    }

    public function testOnLoginFailureWithUnknownIpAndUserAgent(): void
    {
        // Request with loopback IP (default) and no User-Agent
        $request = Request::create('/admin/login', 'POST');
        // Clear the REMOTE_ADDR to simulate missing IP
        $request->server->remove('REMOTE_ADDR');

        $exception = new AuthenticationException('Authentication failed.');

        $passport = new Passport(
            new UserBadge('test@example.com'),
            new PasswordCredentials('password')
        );

        $event = new LoginFailureEvent(
            exception: $exception,
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin',
            passport: $passport
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertSame('unknown', $auditEvent->ipAddress);
                $this->assertSame('Symfony', $auditEvent->userAgent);

                return true;
            }));

        $this->subscriber->onLoginFailure($event);
    }
}
