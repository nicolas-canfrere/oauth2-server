<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Model\OAuthRefreshToken;
use App\Repository\RefreshTokenRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for RefreshTokenRepository.
 *
 * Tests CRUD operations, revocation, expiration handling, and user token management.
 */
final class RefreshTokenRepositoryTest extends KernelTestCase
{
    private RefreshTokenRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->repository = $container->get(RefreshTokenRepository::class);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindByToken(): void
    {
        $refreshToken = $this->createTestRefreshToken(
            id: '123e4567-e89b-12d3-a456-426614174001',
            token: 'refresh_token_test_001',
            userId: 'user_123',
            scopes: ['user:read', 'user:write'],
        );

        $this->repository->create($refreshToken);

        $foundToken = $this->repository->findByToken('refresh_token_test_001');

        $this->assertNotNull($foundToken);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundToken->id);
        $this->assertSame('refresh_token_test_001', $foundToken->token);
        $this->assertSame('client_id_001', $foundToken->clientId);
        $this->assertSame('user_123', $foundToken->userId);
        $this->assertSame(['user:read', 'user:write'], $foundToken->scopes);
        $this->assertFalse($foundToken->isRevoked);
    }

    public function testFindByTokenNonExistent(): void
    {
        $result = $this->repository->findByToken('non_existent_token');

        $this->assertNull($result);
    }

    public function testRevokeToken(): void
    {
        $refreshToken = $this->createTestRefreshToken(
            id: '223e4567-e89b-12d3-a456-426614174002',
            token: 'refresh_token_revoke_001',
            isRevoked: false,
        );

        $this->repository->create($refreshToken);

        // Verify token is not revoked initially
        $foundToken = $this->repository->findByToken('refresh_token_revoke_001');
        $this->assertNotNull($foundToken);
        $this->assertFalse($foundToken->isRevoked);

        // Revoke token
        $revokeResult = $this->repository->revoke('refresh_token_revoke_001');

        $this->assertTrue($revokeResult);

        // Verify token is now revoked
        $revokedToken = $this->repository->findByToken('refresh_token_revoke_001');
        $this->assertNotNull($revokedToken);
        $this->assertTrue($revokedToken->isRevoked);
    }

    public function testRevokeNonExistentToken(): void
    {
        $result = $this->repository->revoke('non_existent_token');

        $this->assertFalse($result);
    }

    public function testFindActiveByUser(): void
    {
        // Create active tokens for user_123
        $activeToken1 = $this->createTestRefreshToken(
            id: '323e4567-e89b-12d3-a456-426614174003',
            token: 'active_token_001',
            userId: 'user_123',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $activeToken2 = $this->createTestRefreshToken(
            id: '323e4567-e89b-12d3-a456-426614174004',
            token: 'active_token_002',
            userId: 'user_123',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('+15 days'),
        );

        // Create revoked token for user_123 (should not be returned)
        $revokedToken = $this->createTestRefreshToken(
            id: '323e4567-e89b-12d3-a456-426614174005',
            token: 'revoked_token_001',
            userId: 'user_123',
            isRevoked: true,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        // Create expired token for user_123 (should not be returned)
        $expiredToken = $this->createTestRefreshToken(
            id: '323e4567-e89b-12d3-a456-426614174006',
            token: 'expired_token_001',
            userId: 'user_123',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        // Create active token for different user (should not be returned)
        $otherUserToken = $this->createTestRefreshToken(
            id: '323e4567-e89b-12d3-a456-426614174007',
            token: 'other_user_token_001',
            userId: 'user_456',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $this->repository->create($activeToken1);
        $this->repository->create($activeToken2);
        $this->repository->create($revokedToken);
        $this->repository->create($expiredToken);
        $this->repository->create($otherUserToken);

        $activeTokens = $this->repository->findActiveByUser('user_123');

        $this->assertCount(2, $activeTokens);

        // Verify tokens are sorted by created_at DESC (check by ID since tokens are redacted)
        $this->assertSame('323e4567-e89b-12d3-a456-426614174004', $activeTokens[0]->id);
        $this->assertSame('323e4567-e89b-12d3-a456-426614174003', $activeTokens[1]->id);

        // Note: token values are redacted in findActiveByUser() for security
        $this->assertSame('***REDACTED***', $activeTokens[0]->token);
        $this->assertSame('***REDACTED***', $activeTokens[1]->token);
    }

    public function testFindActiveByUserNoTokens(): void
    {
        $activeTokens = $this->repository->findActiveByUser('user_no_tokens');

        $this->assertCount(0, $activeTokens);
    }

    public function testDeleteExpired(): void
    {
        // Create expired tokens
        $expiredToken1 = $this->createTestRefreshToken(
            id: '423e4567-e89b-12d3-a456-426614174008',
            token: 'expired_token_002',
            expiresAt: new \DateTimeImmutable('-2 hours'),
        );

        $expiredToken2 = $this->createTestRefreshToken(
            id: '423e4567-e89b-12d3-a456-426614174009',
            token: 'expired_token_003',
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        // Create valid token
        $validToken = $this->createTestRefreshToken(
            id: '423e4567-e89b-12d3-a456-426614174010',
            token: 'valid_token_001',
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $this->repository->create($expiredToken1);
        $this->repository->create($expiredToken2);
        $this->repository->create($validToken);

        // Delete expired tokens
        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(2, $deletedCount);

        // Verify expired tokens are deleted
        $this->assertNull($this->repository->findByToken('expired_token_002'));
        $this->assertNull($this->repository->findByToken('expired_token_003'));

        // Verify valid token still exists
        $this->assertNotNull($this->repository->findByToken('valid_token_001'));
    }

    public function testDeleteExpiredWithNoExpiredTokens(): void
    {
        $validToken = $this->createTestRefreshToken(
            id: '523e4567-e89b-12d3-a456-426614174011',
            token: 'valid_token_002',
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $this->repository->create($validToken);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(0, $deletedCount);
        $this->assertNotNull($this->repository->findByToken('valid_token_002'));
    }

    public function testTokenIsExpiredCheck(): void
    {
        $expiredToken = $this->createTestRefreshToken(
            id: '623e4567-e89b-12d3-a456-426614174012',
            token: 'expired_check_001',
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $validToken = $this->createTestRefreshToken(
            id: '623e4567-e89b-12d3-a456-426614174013',
            token: 'valid_check_001',
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($validToken->isExpired());
    }

    public function testTokenIsValidCheck(): void
    {
        $validToken = $this->createTestRefreshToken(
            id: '723e4567-e89b-12d3-a456-426614174014',
            token: 'valid_complete_001',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $revokedToken = $this->createTestRefreshToken(
            id: '723e4567-e89b-12d3-a456-426614174015',
            token: 'revoked_complete_001',
            isRevoked: true,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $expiredToken = $this->createTestRefreshToken(
            id: '723e4567-e89b-12d3-a456-426614174016',
            token: 'expired_complete_001',
            isRevoked: false,
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        $this->assertTrue($validToken->isValid());
        $this->assertFalse($revokedToken->isValid());
        $this->assertFalse($expiredToken->isValid());
    }

    public function testEmptyScopes(): void
    {
        $refreshToken = $this->createTestRefreshToken(
            id: '823e4567-e89b-12d3-a456-426614174017',
            token: 'empty_scopes_token',
            scopes: [],
        );

        $this->repository->create($refreshToken);

        $foundToken = $this->repository->findByToken('empty_scopes_token');

        $this->assertNotNull($foundToken);
        $this->assertSame([], $foundToken->scopes);
    }

    public function testMultipleScopes(): void
    {
        $refreshToken = $this->createTestRefreshToken(
            id: '923e4567-e89b-12d3-a456-426614174018',
            token: 'multiple_scopes_token',
            scopes: ['user:read', 'user:write', 'admin:all', 'api:access'],
        );

        $this->repository->create($refreshToken);

        $foundToken = $this->repository->findByToken('multiple_scopes_token');

        $this->assertNotNull($foundToken);
        $this->assertSame(['user:read', 'user:write', 'admin:all', 'api:access'], $foundToken->scopes);
    }

    public function testDifferentClients(): void
    {
        $token1 = $this->createTestRefreshToken(
            id: 'a23e4567-e89b-12d3-a456-426614174019',
            token: 'client1_token',
            clientId: 'client_001',
        );

        $token2 = $this->createTestRefreshToken(
            id: 'a23e4567-e89b-12d3-a456-426614174020',
            token: 'client2_token',
            clientId: 'client_002',
        );

        $this->repository->create($token1);
        $this->repository->create($token2);

        $found1 = $this->repository->findByToken('client1_token');
        $found2 = $this->repository->findByToken('client2_token');

        $this->assertNotNull($found1);
        $this->assertNotNull($found2);
        $this->assertSame('client_001', $found1->clientId);
        $this->assertSame('client_002', $found2->clientId);
    }

    public function testCreatedAtTimestampPreserved(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-15 10:30:00');

        $refreshToken = $this->createTestRefreshToken(
            id: 'b23e4567-e89b-12d3-a456-426614174021',
            token: 'timestamp_token',
            createdAt: $createdAt,
        );

        $this->repository->create($refreshToken);

        $foundToken = $this->repository->findByToken('timestamp_token');

        $this->assertNotNull($foundToken);
        $this->assertSame('2025-01-15 10:30:00', $foundToken->createdAt->format('Y-m-d H:i:s'));
    }

    public function testRevokeTokenDoesNotAffectOtherTokens(): void
    {
        $token1 = $this->createTestRefreshToken(
            id: 'c23e4567-e89b-12d3-a456-426614174022',
            token: 'token_revoke_isolation_001',
            isRevoked: false,
        );

        $token2 = $this->createTestRefreshToken(
            id: 'c23e4567-e89b-12d3-a456-426614174023',
            token: 'token_revoke_isolation_002',
            isRevoked: false,
        );

        $this->repository->create($token1);
        $this->repository->create($token2);

        // Revoke only first token
        $this->repository->revoke('token_revoke_isolation_001');

        // Verify first token is revoked
        $found1 = $this->repository->findByToken('token_revoke_isolation_001');
        $this->assertNotNull($found1);
        $this->assertTrue($found1->isRevoked);

        // Verify second token is not affected
        $found2 = $this->repository->findByToken('token_revoke_isolation_002');
        $this->assertNotNull($found2);
        $this->assertFalse($found2->isRevoked);
    }

    /**
     * @param list<string> $scopes
     */
    private function createTestRefreshToken(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $token = 'test_refresh_token',
        string $clientId = 'client_id_001',
        string $userId = 'user_id_001',
        array $scopes = ['user:read'],
        bool $isRevoked = false,
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $createdAt = null,
    ): OAuthRefreshToken {
        return new OAuthRefreshToken(
            id: $id,
            token: $token,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
            isRevoked: $isRevoked,
            expiresAt: $expiresAt ?? new \DateTimeImmutable('+30 days'),
            createdAt: $createdAt ?? new \DateTimeImmutable(),
        );
    }
}
