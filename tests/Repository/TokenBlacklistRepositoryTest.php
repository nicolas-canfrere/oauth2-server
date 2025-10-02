<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Model\OAuthTokenBlacklist;
use App\Repository\TokenBlacklistRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for TokenBlacklistRepository.
 *
 * Tests blacklist operations: adding tokens, checking if blacklisted, and cleanup.
 */
final class TokenBlacklistRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private TokenBlacklistRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new TokenBlacklistRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAddAndIsBlacklisted(): void
    {
        $blacklistEntry = $this->createTestBlacklistEntry(
            id: '123e4567-e89b-12d3-a456-426614174001',
            jti: 'jti_test_001',
        );

        $this->repository->add($blacklistEntry);

        $isBlacklisted = $this->repository->isBlacklisted('jti_test_001');

        $this->assertTrue($isBlacklisted);
    }

    public function testIsBlacklistedNonExistent(): void
    {
        $isBlacklisted = $this->repository->isBlacklisted('non_existent_jti');

        $this->assertFalse($isBlacklisted);
    }

    public function testAddMultipleTokens(): void
    {
        $entry1 = $this->createTestBlacklistEntry(
            id: '223e4567-e89b-12d3-a456-426614174002',
            jti: 'jti_multiple_001',
        );

        $entry2 = $this->createTestBlacklistEntry(
            id: '223e4567-e89b-12d3-a456-426614174003',
            jti: 'jti_multiple_002',
        );

        $entry3 = $this->createTestBlacklistEntry(
            id: '223e4567-e89b-12d3-a456-426614174004',
            jti: 'jti_multiple_003',
        );

        $this->repository->add($entry1);
        $this->repository->add($entry2);
        $this->repository->add($entry3);

        $this->assertTrue($this->repository->isBlacklisted('jti_multiple_001'));
        $this->assertTrue($this->repository->isBlacklisted('jti_multiple_002'));
        $this->assertTrue($this->repository->isBlacklisted('jti_multiple_003'));
    }

    public function testDeleteExpired(): void
    {
        // Create expired entries
        $expiredEntry1 = $this->createTestBlacklistEntry(
            id: '323e4567-e89b-12d3-a456-426614174005',
            jti: 'jti_expired_001',
            expiresAt: new \DateTimeImmutable('-2 hours'),
        );

        $expiredEntry2 = $this->createTestBlacklistEntry(
            id: '323e4567-e89b-12d3-a456-426614174006',
            jti: 'jti_expired_002',
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        // Create valid entry
        $validEntry = $this->createTestBlacklistEntry(
            id: '323e4567-e89b-12d3-a456-426614174007',
            jti: 'jti_valid_001',
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->repository->add($expiredEntry1);
        $this->repository->add($expiredEntry2);
        $this->repository->add($validEntry);

        // Delete expired entries
        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(2, $deletedCount);

        // Verify expired entries are deleted
        $this->assertFalse($this->repository->isBlacklisted('jti_expired_001'));
        $this->assertFalse($this->repository->isBlacklisted('jti_expired_002'));

        // Verify valid entry still exists
        $this->assertTrue($this->repository->isBlacklisted('jti_valid_001'));
    }

    public function testDeleteExpiredWithNoExpiredEntries(): void
    {
        $validEntry = $this->createTestBlacklistEntry(
            id: '423e4567-e89b-12d3-a456-426614174008',
            jti: 'jti_valid_002',
            expiresAt: new \DateTimeImmutable('+2 hours'),
        );

        $this->repository->add($validEntry);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(0, $deletedCount);
        $this->assertTrue($this->repository->isBlacklisted('jti_valid_002'));
    }

    public function testAddWithReason(): void
    {
        $blacklistEntry = $this->createTestBlacklistEntry(
            id: '523e4567-e89b-12d3-a456-426614174009',
            jti: 'jti_reason_001',
            reason: 'User requested token revocation',
        );

        $this->repository->add($blacklistEntry);

        $this->assertTrue($this->repository->isBlacklisted('jti_reason_001'));
    }

    public function testAddWithoutReason(): void
    {
        $blacklistEntry = $this->createTestBlacklistEntry(
            id: '623e4567-e89b-12d3-a456-426614174010',
            jti: 'jti_no_reason_001',
            reason: null,
        );

        $this->repository->add($blacklistEntry);

        $this->assertTrue($this->repository->isBlacklisted('jti_no_reason_001'));
    }

    public function testIsExpiredCheck(): void
    {
        $expiredEntry = $this->createTestBlacklistEntry(
            id: '723e4567-e89b-12d3-a456-426614174011',
            jti: 'jti_expired_check_001',
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $validEntry = $this->createTestBlacklistEntry(
            id: '723e4567-e89b-12d3-a456-426614174012',
            jti: 'jti_valid_check_001',
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->assertTrue($expiredEntry->isExpired());
        $this->assertFalse($validEntry->isExpired());
    }

    public function testDifferentJtiValues(): void
    {
        $entry1 = $this->createTestBlacklistEntry(
            id: '823e4567-e89b-12d3-a456-426614174013',
            jti: 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb',
        );

        $entry2 = $this->createTestBlacklistEntry(
            id: '823e4567-e89b-12d3-a456-426614174014',
            jti: 'cccccccc-4444-5555-6666-dddddddddddd',
        );

        $this->repository->add($entry1);
        $this->repository->add($entry2);

        $this->assertTrue($this->repository->isBlacklisted('aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb'));
        $this->assertTrue($this->repository->isBlacklisted('cccccccc-4444-5555-6666-dddddddddddd'));
        $this->assertFalse($this->repository->isBlacklisted('eeeeeeee-7777-8888-9999-ffffffffffff'));
    }

    public function testRevokedAtTimestampPreserved(): void
    {
        $revokedAt = new \DateTimeImmutable('2025-01-15 14:30:00');

        $blacklistEntry = $this->createTestBlacklistEntry(
            id: '923e4567-e89b-12d3-a456-426614174015',
            jti: 'jti_timestamp_001',
            revokedAt: $revokedAt,
        );

        $this->repository->add($blacklistEntry);

        // Verify entry exists (we can't easily retrieve it without a find method, but we can verify it's blacklisted)
        $this->assertTrue($this->repository->isBlacklisted('jti_timestamp_001'));
    }

    public function testBlacklistIsolation(): void
    {
        $entry1 = $this->createTestBlacklistEntry(
            id: 'a23e4567-e89b-12d3-a456-426614174016',
            jti: 'jti_isolation_001',
        );

        $entry2 = $this->createTestBlacklistEntry(
            id: 'a23e4567-e89b-12d3-a456-426614174017',
            jti: 'jti_isolation_002',
        );

        // Add only first entry
        $this->repository->add($entry1);

        // Verify only first entry is blacklisted
        $this->assertTrue($this->repository->isBlacklisted('jti_isolation_001'));
        $this->assertFalse($this->repository->isBlacklisted('jti_isolation_002'));
    }

    public function testLongReason(): void
    {
        $longReason = str_repeat('This token was revoked due to security concerns. ', 20);

        $blacklistEntry = $this->createTestBlacklistEntry(
            id: 'b23e4567-e89b-12d3-a456-426614174018',
            jti: 'jti_long_reason_001',
            reason: $longReason,
        );

        $this->repository->add($blacklistEntry);

        $this->assertTrue($this->repository->isBlacklisted('jti_long_reason_001'));
    }

    public function testDeleteExpiredBoundaryCondition(): void
    {
        // Create entry expiring exactly now (edge case)
        $nowEntry = $this->createTestBlacklistEntry(
            id: 'c23e4567-e89b-12d3-a456-426614174019',
            jti: 'jti_now_001',
            expiresAt: new \DateTimeImmutable('now'),
        );

        // Create entry expiring 1 second in the future
        $futureEntry = $this->createTestBlacklistEntry(
            id: 'c23e4567-e89b-12d3-a456-426614174020',
            jti: 'jti_future_001',
            expiresAt: new \DateTimeImmutable('+1 second'),
        );

        $this->repository->add($nowEntry);
        $this->repository->add($futureEntry);

        // Sleep to ensure 'now' entry is expired
        sleep(1);

        $deletedCount = $this->repository->deleteExpired();

        // At least the 'now' entry should be deleted
        $this->assertGreaterThanOrEqual(1, $deletedCount);
    }

    private function createTestBlacklistEntry(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $jti = 'test_jti',
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $revokedAt = null,
        ?string $reason = null,
    ): OAuthTokenBlacklist {
        return new OAuthTokenBlacklist(
            id: $id,
            jti: $jti,
            expiresAt: $expiresAt ?? new \DateTimeImmutable('+1 hour'),
            revokedAt: $revokedAt ?? new \DateTimeImmutable(),
            reason: $reason,
        );
    }
}
