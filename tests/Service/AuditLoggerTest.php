<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\AuditEventDTO;
use App\Enum\AuditEventTypeEnum;
use App\Model\OAuthAuditLog;
use App\Repository\AuditLogRepositoryInterface;
use App\Service\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for AuditLogger service.
 *
 * Validates dual backend logging (file + database) and proper event handling.
 */
final class AuditLoggerTest extends TestCase
{
    private AuditLogger $auditLogger;
    private LoggerInterface&MockObject $mockFileLogger;
    private AuditLogRepositoryInterface&MockObject $mockRepository;

    protected function setUp(): void
    {
        $this->mockFileLogger = $this->createMock(LoggerInterface::class);
        $this->mockRepository = $this->createMock(AuditLogRepositoryInterface::class);

        $this->auditLogger = new AuditLogger(
            $this->mockFileLogger,
            $this->mockRepository
        );
    }

    public function testLogWritesToBothFileAndDatabase(): void
    {
        $event = AuditEventDTO::loginSuccess(
            userId: 'user-123',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0',
            additionalContext: ['session_id' => 'sess-456']
        );

        // Assert file logger is called with correct level and message
        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('info'),
                $this->equalTo('User logged in successfully'),
                $this->callback(function (array $context) {
                    $this->assertSame('auth.login.success', $context['event_type']);
                    $this->assertSame('user-123', $context['user_id']);
                    $this->assertSame('192.168.1.100', $context['ip_address']);
                    $this->assertSame('Mozilla/5.0', $context['user_agent']);
                    $this->assertArrayHasKey('timestamp', $context);
                    $this->assertSame(['session_id' => 'sess-456'], $context['context']);

                    return true;
                })
            );

        // Assert database repository create is called
        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (OAuthAuditLog $log) {
                $this->assertSame(AuditEventTypeEnum::LOGIN_SUCCESS, $log->eventType);
                $this->assertSame('info', $log->level);
                $this->assertSame('User logged in successfully', $log->message);
                $this->assertSame('user-123', $log->userId);
                $this->assertSame('192.168.1.100', $log->ipAddress);
                $this->assertSame('Mozilla/5.0', $log->userAgent);
                $this->assertSame(['session_id' => 'sess-456'], $log->context);

                return true;
            }));

        $this->auditLogger->log($event);
    }

    public function testLogLoginFailureWithWarningLevel(): void
    {
        $event = AuditEventDTO::loginFailure(
            ipAddress: '10.0.0.50',
            userAgent: 'curl/7.68.0',
            reason: 'Invalid credentials',
            additionalContext: ['attempt' => 3]
        );

        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('warning'),
                $this->stringContains('Invalid credentials'),
                $this->callback(fn(array $context) => 'auth.login.failure' === $context['event_type'])
            );

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (OAuthAuditLog $log) {
                $this->assertSame(AuditEventTypeEnum::LOGIN_FAILURE, $log->eventType);
                $this->assertSame('warning', $log->level);
                $this->assertNull($log->userId);
                $this->assertSame('10.0.0.50', $log->ipAddress);

                return true;
            }));

        $this->auditLogger->log($event);
    }

    public function testLogAccessTokenIssued(): void
    {
        $event = AuditEventDTO::accessTokenIssued(
            userId: 'user-789',
            clientId: 'client-abc',
            jti: 'jti-unique-id',
            scopes: ['read', 'write'],
            ipAddress: '172.16.0.1',
            additionalContext: ['grant_type' => 'authorization_code']
        );

        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('info'),
                $this->equalTo('Access token issued'),
                $this->callback(function (array $context) {
                    $this->assertSame('user-789', $context['user_id']);
                    $this->assertSame('client-abc', $context['client_id']);
                    $this->assertIsArray($context['context']);
                    $this->assertArrayHasKey('jti', $context['context']);
                    $this->assertArrayHasKey('scopes', $context['context']);
                    $this->assertSame('jti-unique-id', $context['context']['jti']);
                    $this->assertSame(['read', 'write'], $context['context']['scopes']);

                    return true;
                })
            );

        $this->mockRepository
            ->expects($this->once())
            ->method('create');

        $this->auditLogger->log($event);
    }

    public function testLogTokenRevoked(): void
    {
        $event = AuditEventDTO::tokenRevoked(
            tokenType: AuditEventTypeEnum::REFRESH_TOKEN_REVOKED,
            tokenIdentifier: 'token-xyz',
            reason: 'User logout',
            userId: 'user-123',
            clientId: 'client-def',
            ipAddress: '192.168.1.200'
        );

        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->stringContains('User logout'),
                $this->callback(fn(array $context) => 'token.refresh.revoked' === $context['event_type'])
            );

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (OAuthAuditLog $log) {
                $this->assertSame(AuditEventTypeEnum::REFRESH_TOKEN_REVOKED, $log->eventType);
                $this->assertSame('notice', $log->level);
                $this->assertSame('user-123', $log->userId);
                $this->assertSame('client-def', $log->clientId);

                return true;
            }));

        $this->auditLogger->log($event);
    }

    public function testLogRateLimitExceeded(): void
    {
        $event = AuditEventDTO::rateLimitExceeded(
            limiterName: 'oauth_token',
            ipAddress: '203.0.113.50',
            userId: null,
            additionalContext: ['limit' => 20, 'window' => '1 minute']
        );

        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('warning'),
                $this->stringContains('oauth_token'),
                $this->callback(fn(array $context) => 'security.rate_limit.exceeded' === $context['event_type'])
            );

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (OAuthAuditLog $log) {
                $this->assertSame(AuditEventTypeEnum::RATE_LIMIT_EXCEEDED, $log->eventType);
                $this->assertNull($log->userId);
                $this->assertSame('203.0.113.50', $log->ipAddress);

                return true;
            }));

        $this->auditLogger->log($event);
    }

    public function testLogBatchWritesMultipleEvents(): void
    {
        $events = [
            AuditEventDTO::loginSuccess(
                userId: 'user-1',
                ipAddress: '10.0.0.1',
                userAgent: 'Browser1'
            ),
            AuditEventDTO::loginSuccess(
                userId: 'user-2',
                ipAddress: '10.0.0.2',
                userAgent: 'Browser2'
            ),
            AuditEventDTO::loginFailure(
                ipAddress: '10.0.0.3',
                userAgent: 'Browser3',
                reason: 'Invalid password'
            ),
        ];

        // Expect 3 file log calls
        $this->mockFileLogger
            ->expects($this->exactly(3))
            ->method('log');

        // Expect 3 database create calls
        $this->mockRepository
            ->expects($this->exactly(3))
            ->method('create');

        $this->auditLogger->logBatch($events);
    }

    public function testLogMapsAllLogLevelsCorrectly(): void
    {
        $testCases = [
            ['level' => 'emergency', 'expectedPsrLevel' => \Psr\Log\LogLevel::EMERGENCY],
            ['level' => 'alert', 'expectedPsrLevel' => \Psr\Log\LogLevel::ALERT],
            ['level' => 'critical', 'expectedPsrLevel' => \Psr\Log\LogLevel::CRITICAL],
            ['level' => 'error', 'expectedPsrLevel' => \Psr\Log\LogLevel::ERROR],
            ['level' => 'warning', 'expectedPsrLevel' => \Psr\Log\LogLevel::WARNING],
            ['level' => 'notice', 'expectedPsrLevel' => \Psr\Log\LogLevel::NOTICE],
            ['level' => 'info', 'expectedPsrLevel' => \Psr\Log\LogLevel::INFO],
            ['level' => 'debug', 'expectedPsrLevel' => \Psr\Log\LogLevel::DEBUG],
        ];

        // Test each log level individually to avoid deprecated at() method
        foreach ($testCases as $testCase) {
            $mockLogger = $this->createMock(LoggerInterface::class);
            $mockRepository = $this->createMock(AuditLogRepositoryInterface::class);
            $auditLogger = new AuditLogger($mockLogger, $mockRepository);

            $event = new AuditEventDTO(
                eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
                level: $testCase['level'],
                message: 'Test message',
                context: [],
                userId: 'user-test',
                ipAddress: '127.0.0.1'
            );

            $mockLogger
                ->expects($this->once())
                ->method('log')
                ->with($this->equalTo($testCase['expectedPsrLevel']));

            $mockRepository
                ->expects($this->once())
                ->method('create');

            $auditLogger->log($event);
        }
    }

    public function testLogHandlesMissingOptionalFields(): void
    {
        $event = new AuditEventDTO(
            eventType: AuditEventTypeEnum::CLIENT_CREATED,
            level: 'info',
            message: 'Client created by admin',
            context: ['admin_id' => 'admin-1'],
            userId: null,
            clientId: null,
            ipAddress: null,
            userAgent: null
        );

        $this->mockFileLogger
            ->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $context) {
                    $this->assertNull($context['user_id']);
                    $this->assertNull($context['client_id']);
                    $this->assertNull($context['ip_address']);
                    $this->assertNull($context['user_agent']);

                    return true;
                })
            );

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (OAuthAuditLog $log) {
                $this->assertNull($log->userId);
                $this->assertNull($log->clientId);
                $this->assertNull($log->ipAddress);
                $this->assertNull($log->userAgent);

                return true;
            }));

        $this->auditLogger->log($event);
    }
}
