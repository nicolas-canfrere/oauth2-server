<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\DTO\AuditEventDTO;
use App\Enum\AuditEventTypeEnum;
use App\EventSubscriber\AuthenticationSuccessSubscriber;
use App\Security\SecurityUser;
use App\Service\AuditLoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Unit tests for AuthenticationSuccessSubscriber.
 *
 * Validates that successful authentication events are properly logged to audit trail.
 */
final class AuthenticationSuccessSubscriberTest extends TestCase
{
    private AuditLoggerInterface&MockObject $mockAuditLogger;
    private AuthenticationSuccessSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mockAuditLogger = $this->createMock(AuditLoggerInterface::class);

        $this->subscriber = new AuthenticationSuccessSubscriber(
            $this->mockAuditLogger
        );
    }

    public function testSubscribesToLoginSuccessEvent(): void
    {
        $subscribedEvents = AuthenticationSuccessSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginSuccessEvent::class, $subscribedEvents);
        $this->assertSame('onLoginSuccess', $subscribedEvents[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessLogsAuditEvent(): void
    {
        $securityUser = new SecurityUser(
            userIdentifier: 'test@example.com',
            passwordHash: 'hashed-password',
            roles: ['ROLE_USER'],
            userId: 'user-123'
        );

        $request = Request::create('/admin/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);

        $passport = new SelfValidatingPassport(new UserBadge('test@example.com', fn() => $securityUser));
        $event = new LoginSuccessEvent(
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            passport: $passport,
            authenticatedToken: $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin'
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertSame(AuditEventTypeEnum::LOGIN_SUCCESS, $auditEvent->eventType);
                $this->assertSame('info', $auditEvent->level);
                $this->assertSame('User logged in successfully', $auditEvent->message);
                $this->assertSame('user-123', $auditEvent->userId);
                $this->assertSame('192.168.1.100', $auditEvent->ipAddress);
                $this->assertSame('Mozilla/5.0', $auditEvent->userAgent);
                $this->assertArrayHasKey('firewall', $auditEvent->context);
                $this->assertSame('admin', $auditEvent->context['firewall']);
                $this->assertArrayHasKey('authenticated_at', $auditEvent->context);

                return true;
            }));

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessWithUnknownIpAndUserAgent(): void
    {
        $securityUser = new SecurityUser(
            userIdentifier: 'user@example.com',
            passwordHash: 'hashed-password',
            roles: ['ROLE_USER'],
            userId: 'user-456'
        );

        // Request with cleared IP and no User-Agent
        $request = Request::create('/admin/login', 'POST');
        $request->server->remove('REMOTE_ADDR');

        $passport = new SelfValidatingPassport(new UserBadge('user@example.com', fn() => $securityUser));
        $event = new LoginSuccessEvent(
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            passport: $passport,
            authenticatedToken: $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class),
            request: $request,
            response: null,
            firewallName: 'main'
        );

        $this->mockAuditLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->callback(function (AuditEventDTO $auditEvent) {
                $this->assertSame('unknown', $auditEvent->ipAddress);
                $this->assertSame('Symfony', $auditEvent->userAgent);

                return true;
            }));

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessDoesNotLogIfUserIsNotSecurityUser(): void
    {
        $genericUser = $this->createMock(UserInterface::class);

        $request = Request::create('/admin/login', 'POST');

        $passport = new SelfValidatingPassport(new UserBadge('test@example.com', fn() => $genericUser));
        $event = new LoginSuccessEvent(
            authenticator: $this->createMock(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class),
            passport: $passport,
            authenticatedToken: $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class),
            request: $request,
            response: null,
            firewallName: 'admin'
        );

        $this->mockAuditLogger
            ->expects($this->never())
            ->method('log');

        $this->subscriber->onLoginSuccess($event);
    }
}
