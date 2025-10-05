<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Infrastructure\Persistance\Doctrine\Repository\ConsentRepository;
use App\Model\UserConsent;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for ConsentRepository.
 *
 * Tests CRUD operations and consent management using UserConsent model.
 */
final class ConsentRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private ConsentRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new ConsentRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testSaveAndFindConsent(): void
    {
        $consent = $this->createTestConsent(
            id: '123e4567-e89b-12d3-a456-426614174001',
            userId: 'user-001',
            clientId: 'client-001',
            scopes: ['user:read', 'user:write'],
        );

        $this->repository->saveConsent($consent);

        $foundConsent = $this->repository->findConsent('user-001', 'client-001');

        $this->assertNotNull($foundConsent);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundConsent->id);
        $this->assertSame('user-001', $foundConsent->userId);
        $this->assertSame('client-001', $foundConsent->clientId);
        $this->assertSame(['user:read', 'user:write'], $foundConsent->scopes);
    }

    public function testFindConsentNonExistent(): void
    {
        $result = $this->repository->findConsent('non-existent-user', 'non-existent-client');

        $this->assertNull($result);
    }

    public function testUpdateConsent(): void
    {
        $consent = $this->createTestConsent(
            id: '223e4567-e89b-12d3-a456-426614174002',
            userId: 'user-002',
            clientId: 'client-002',
            scopes: ['user:read'],
        );

        $this->repository->saveConsent($consent);

        // Update with new scopes
        $updatedConsent = new UserConsent(
            id: '223e4567-e89b-12d3-a456-426614174002',
            userId: 'user-002',
            clientId: 'client-002',
            scopes: ['user:read', 'user:write', 'admin:all'],
            grantedAt: new \DateTimeImmutable(),
            expiresAt: (new \DateTimeImmutable())->modify('+60 days'),
        );

        $this->repository->saveConsent($updatedConsent);

        $foundConsent = $this->repository->findConsent('user-002', 'client-002');

        $this->assertNotNull($foundConsent);
        $this->assertSame(['user:read', 'user:write', 'admin:all'], $foundConsent->scopes);
    }

    public function testRevokeConsent(): void
    {
        $consent = $this->createTestConsent(
            id: '323e4567-e89b-12d3-a456-426614174003',
            userId: 'user-003',
            clientId: 'client-003',
        );

        $this->repository->saveConsent($consent);

        $this->assertNotNull($this->repository->findConsent('user-003', 'client-003'));

        $revokeResult = $this->repository->revokeConsent('user-003', 'client-003');

        $this->assertTrue($revokeResult);
        $this->assertNull($this->repository->findConsent('user-003', 'client-003'));
    }

    public function testRevokeNonExistentConsent(): void
    {
        $result = $this->repository->revokeConsent('non-existent-user', 'non-existent-client');

        $this->assertFalse($result);
    }

    public function testFindByUser(): void
    {
        $consents = [
            ['userId' => 'user-004', 'clientId' => 'client-001', 'scopes' => ['user:read']],
            ['userId' => 'user-004', 'clientId' => 'client-002', 'scopes' => ['user:write']],
            ['userId' => 'user-004', 'clientId' => 'client-003', 'scopes' => ['admin:all']],
        ];

        foreach ($consents as $index => $consentData) {
            $consent = $this->createTestConsent(
                id: sprintf('423e4567-e89b-12d3-a456-42661417400%d', $index + 1),
                userId: $consentData['userId'],
                clientId: $consentData['clientId'],
                scopes: $consentData['scopes'],
            );
            $this->repository->saveConsent($consent);
        }

        $userConsents = $this->repository->findByUser('user-004');

        $this->assertCount(3, $userConsents);

        // Verify all consents belong to the same user
        foreach ($userConsents as $consent) {
            $this->assertSame('user-004', $consent->userId);
        }
    }

    public function testFindByUserWithNoConsents(): void
    {
        $userConsents = $this->repository->findByUser('user-without-consents');

        $this->assertSame([], $userConsents);
    }

    public function testDeleteExpired(): void
    {
        // Create expired consent
        $expiredConsent = new UserConsent(
            id: '523e4567-e89b-12d3-a456-426614174005',
            userId: 'user-005',
            clientId: 'client-005',
            scopes: ['user:read'],
            grantedAt: (new \DateTimeImmutable())->modify('-60 days'),
            expiresAt: (new \DateTimeImmutable())->modify('-1 day'),
        );
        $this->repository->saveConsent($expiredConsent);

        // Create valid consent
        $validConsent = $this->createTestConsent(
            id: '623e4567-e89b-12d3-a456-426614174006',
            userId: 'user-006',
            clientId: 'client-006',
        );
        $this->repository->saveConsent($validConsent);

        // Delete expired consents
        $deletedCount = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify expired consent is deleted
        $this->assertNull($this->repository->findConsent('user-005', 'client-005'));

        // Verify valid consent remains
        $this->assertNotNull($this->repository->findConsent('user-006', 'client-006'));
    }

    public function testDeleteExpiredWithNoExpiredConsents(): void
    {
        $consent = $this->createTestConsent(
            id: '723e4567-e89b-12d3-a456-426614174007',
            userId: 'user-007',
            clientId: 'client-007',
        );
        $this->repository->saveConsent($consent);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(0, $deletedCount);
    }

    public function testEmptyScopes(): void
    {
        $consent = $this->createTestConsent(
            id: '823e4567-e89b-12d3-a456-426614174008',
            userId: 'user-008',
            clientId: 'client-008',
            scopes: [],
        );

        $this->repository->saveConsent($consent);

        $foundConsent = $this->repository->findConsent('user-008', 'client-008');

        $this->assertNotNull($foundConsent);
        $this->assertSame([], $foundConsent->scopes);
    }

    public function testMultipleScopes(): void
    {
        $scopes = [
            'user:read',
            'user:write',
            'profile:read',
            'profile:write',
            'admin:all',
            'api:access',
        ];

        $consent = $this->createTestConsent(
            id: '923e4567-e89b-12d3-a456-426614174009',
            userId: 'user-009',
            clientId: 'client-009',
            scopes: $scopes,
        );

        $this->repository->saveConsent($consent);

        $foundConsent = $this->repository->findConsent('user-009', 'client-009');

        $this->assertNotNull($foundConsent);
        $this->assertSame($scopes, $foundConsent->scopes);
    }

    public function testUniqueConstraintOnUserAndClient(): void
    {
        $consent1 = $this->createTestConsent(
            id: 'a23e4567-e89b-12d3-a456-426614174010',
            userId: 'user-010',
            clientId: 'client-010',
            scopes: ['user:read'],
        );
        $this->repository->saveConsent($consent1);

        // Attempt to save different consent with same user_id and client_id (different ID)
        $consent2 = $this->createTestConsent(
            id: 'a23e4567-e89b-12d3-a456-426614174011',
            userId: 'user-010',
            clientId: 'client-010',
            scopes: ['user:write'],
        );

        // This should update the existing consent, not create a new one
        $this->repository->saveConsent($consent2);

        $foundConsent = $this->repository->findConsent('user-010', 'client-010');

        $this->assertNotNull($foundConsent);
        $this->assertSame(['user:write'], $foundConsent->scopes);
    }

    public function testConsentExpiration(): void
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+30 days');

        $consent = new UserConsent(
            id: 'b23e4567-e89b-12d3-a456-426614174012',
            userId: 'user-012',
            clientId: 'client-012',
            scopes: ['user:read'],
            grantedAt: $now,
            expiresAt: $expiresAt,
        );

        $this->repository->saveConsent($consent);

        $foundConsent = $this->repository->findConsent('user-012', 'client-012');

        $this->assertNotNull($foundConsent);
        $this->assertGreaterThan($now, $foundConsent->expiresAt);
    }

    /**
     * @param list<string> $scopes
     */
    private function createTestConsent(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $userId = 'test-user',
        string $clientId = 'test-client',
        array $scopes = ['user:read'],
    ): UserConsent {
        return new UserConsent(
            id: $id,
            userId: $userId,
            clientId: $clientId,
            scopes: $scopes,
            grantedAt: new \DateTimeImmutable(),
            expiresAt: (new \DateTimeImmutable())->modify('+30 days'),
        );
    }
}
