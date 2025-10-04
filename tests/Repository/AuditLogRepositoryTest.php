<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Enum\AuditEventTypeEnum;
use App\Model\OAuthAuditLog;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Integration tests for AuditLogRepository.
 *
 * Tests CRUD operations and query methods using real database.
 */
final class AuditLogRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private AuditLogRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new AuditLogRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindAuditLog(): void
    {
        $userId = Uuid::v4()->toString();

        $auditLog = $this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $userId,
            clientId: null,
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0'
        );

        $this->repository->create($auditLog);

        $found = $this->repository->find($auditLog->id);

        $this->assertNotNull($found);
        $this->assertSame($auditLog->id, $found->id);
        $this->assertSame(AuditEventTypeEnum::LOGIN_SUCCESS, $found->eventType);
        $this->assertSame('info', $found->level);
        $this->assertSame('User logged in successfully', $found->message);
        $this->assertSame($userId, $found->userId);
        $this->assertSame('192.168.1.100', $found->ipAddress);
        $this->assertSame('Mozilla/5.0', $found->userAgent);
    }

    public function testFindNonExistentAuditLog(): void
    {
        $found = $this->repository->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($found);
    }

    public function testFindByUserId(): void
    {
        $userId = Uuid::v4()->toString();
        $otherUserId = Uuid::v4()->toString();

        // Create multiple audit logs for the same user
        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $userId
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::ACCESS_TOKEN_ISSUED,
            userId: $userId,
            clientId: 'client-abc'
        ));

        // Create log for different user
        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $otherUserId
        ));

        $userLogs = $this->repository->findByUserId($userId);

        $this->assertCount(2, $userLogs);
        $this->assertSame($userId, $userLogs[0]->userId);
        $this->assertSame($userId, $userLogs[1]->userId);

        // Verify ordering by created_at DESC (most recent first)
        $this->assertGreaterThanOrEqual(
            $userLogs[1]->createdAt->getTimestamp(),
            $userLogs[0]->createdAt->getTimestamp()
        );
    }

    public function testFindByClientId(): void
    {
        $clientId = 'client-test-123';
        $user1Id = Uuid::v4()->toString();
        $user2Id = Uuid::v4()->toString();

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::ACCESS_TOKEN_ISSUED,
            userId: $user1Id,
            clientId: $clientId
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::REFRESH_TOKEN_ISSUED,
            userId: $user2Id,
            clientId: $clientId
        ));

        $clientLogs = $this->repository->findByClientId($clientId);

        $this->assertCount(2, $clientLogs);
        $this->assertSame($clientId, $clientLogs[0]->clientId);
        $this->assertSame($clientId, $clientLogs[1]->clientId);
    }

    public function testFindByEventType(): void
    {
        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_FAILURE,
            userId: null,
            ipAddress: '10.0.0.1'
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_FAILURE,
            userId: null,
            ipAddress: '10.0.0.2'
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: Uuid::v4()->toString()
        ));

        $failureLogs = $this->repository->findByEventType(AuditEventTypeEnum::LOGIN_FAILURE);

        $this->assertCount(2, $failureLogs);
        $this->assertSame(AuditEventTypeEnum::LOGIN_FAILURE, $failureLogs[0]->eventType);
        $this->assertSame(AuditEventTypeEnum::LOGIN_FAILURE, $failureLogs[1]->eventType);
    }

    public function testFindByDateRange(): void
    {
        $now = new \DateTimeImmutable();
        $twoDaysAgo = $now->modify('-2 days');
        $oneDayAgo = $now->modify('-1 day');
        $tomorrow = $now->modify('+1 day');

        // Create log within range
        $userInRangeId = Uuid::v4()->toString();
        $logInRange = $this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $userInRangeId,
            createdAt: $oneDayAgo
        );
        $this->repository->create($logInRange);

        // Create log outside range (too old)
        $logOutsideRange = $this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: Uuid::v4()->toString(),
            createdAt: $twoDaysAgo->modify('-1 day')
        );
        $this->repository->create($logOutsideRange);

        $logsInRange = $this->repository->findByDateRange($twoDaysAgo, $tomorrow);

        $this->assertCount(1, $logsInRange);
        $this->assertSame($userInRangeId, $logsInRange[0]->userId);
    }

    public function testDeleteOlderThan(): void
    {
        $now = new \DateTimeImmutable();
        $oldDate = $now->modify('-100 days');
        $recentDate = $now->modify('-10 days');

        // Create old log (should be deleted)
        $oldLog = $this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: Uuid::v4()->toString(),
            createdAt: $oldDate
        );
        $this->repository->create($oldLog);

        // Create recent log (should be kept)
        $recentLog = $this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: Uuid::v4()->toString(),
            createdAt: $recentDate
        );
        $this->repository->create($recentLog);

        $cutoffDate = $now->modify('-30 days');
        $deletedCount = $this->repository->deleteOlderThan($cutoffDate);

        $this->assertSame(1, $deletedCount);

        // Verify old log was deleted
        $this->assertNull($this->repository->find($oldLog->id));

        // Verify recent log still exists
        $this->assertNotNull($this->repository->find($recentLog->id));
    }

    public function testCount(): void
    {
        $user1Id = Uuid::v4()->toString();
        $user2Id = Uuid::v4()->toString();

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $user1Id
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $user1Id
        ));

        $this->repository->create($this->createTestAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            userId: $user2Id
        ));

        // Count all logs
        $totalCount = $this->repository->count();
        $this->assertGreaterThanOrEqual(3, $totalCount);

        // Count logs for specific user
        $user1Count = $this->repository->count($user1Id);
        $this->assertSame(2, $user1Count);
    }

    public function testContextJsonbStorage(): void
    {
        $context = [
            'jti' => 'token-123',
            'scopes' => ['read', 'write', 'admin'],
            'grant_type' => 'authorization_code',
            'nested' => ['key' => 'value'],
        ];

        $auditLog = new OAuthAuditLog(
            id: Uuid::v4()->toString(),
            eventType: AuditEventTypeEnum::ACCESS_TOKEN_ISSUED,
            level: 'info',
            message: 'Access token issued',
            context: $context,
            userId: Uuid::v4()->toString(),
            clientId: 'client-json-test',
            ipAddress: '127.0.0.1',
            userAgent: 'Test Agent',
            createdAt: new \DateTimeImmutable()
        );

        $this->repository->create($auditLog);

        $found = $this->repository->find($auditLog->id);

        $this->assertNotNull($found);

        // Verify all context keys are present (order doesn't matter)
        $this->assertArrayHasKey('jti', $found->context);
        $this->assertArrayHasKey('scopes', $found->context);
        $this->assertArrayHasKey('grant_type', $found->context);
        $this->assertArrayHasKey('nested', $found->context);

        $this->assertSame('token-123', $found->context['jti']);
        $this->assertSame(['read', 'write', 'admin'], $found->context['scopes']);
        $this->assertSame('authorization_code', $found->context['grant_type']);
        $this->assertSame(['key' => 'value'], $found->context['nested']);
    }

    /**
     * Create test audit log with sensible defaults.
     */
    private function createTestAuditLog(
        string $id,
        AuditEventTypeEnum $eventType,
        ?string $userId = null,
        ?string $clientId = null,
        ?string $ipAddress = '127.0.0.1',
        ?string $userAgent = 'Test User Agent',
        ?\DateTimeImmutable $createdAt = null,
    ): OAuthAuditLog {
        return new OAuthAuditLog(
            id: $id,
            eventType: $eventType,
            level: match ($eventType) {
                AuditEventTypeEnum::LOGIN_FAILURE,
                AuditEventTypeEnum::INVALID_CLIENT_CREDENTIALS,
                AuditEventTypeEnum::RATE_LIMIT_EXCEEDED => 'warning',
                AuditEventTypeEnum::ACCESS_TOKEN_REVOKED,
                AuditEventTypeEnum::REFRESH_TOKEN_REVOKED => 'notice',
                default => 'info',
            },
            message: match ($eventType) {
                AuditEventTypeEnum::LOGIN_SUCCESS => 'User logged in successfully',
                AuditEventTypeEnum::LOGIN_FAILURE => 'Login failed',
                AuditEventTypeEnum::ACCESS_TOKEN_ISSUED => 'Access token issued',
                AuditEventTypeEnum::REFRESH_TOKEN_ISSUED => 'Refresh token issued',
                AuditEventTypeEnum::ACCESS_TOKEN_REVOKED => 'Access token revoked',
                AuditEventTypeEnum::REFRESH_TOKEN_REVOKED => 'Refresh token revoked',
                default => 'Audit event',
            },
            context: [],
            userId: $userId,
            clientId: $clientId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt ?? new \DateTimeImmutable(),
        );
    }
}
