<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Domain\AuthorizationCode\Model\OAuthAuthorizationCode;
use App\Infrastructure\Persistance\Doctrine\Repository\AuthorizationCodeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for AuthorizationCodeRepository.
 *
 * Tests CRUD operations, expiration handling, and single-use consumption.
 */
final class AuthorizationCodeRepositoryTest extends KernelTestCase
{
    private AuthorizationCodeRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->repository = $container->get(AuthorizationCodeRepository::class);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindByCode(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '123e4567-e89b-12d3-a456-426614174001',
            code: 'auth_code_test_001',
            scopes: ['user:read', 'user:write'],
        );

        $this->repository->create($authCode);

        $foundCode = $this->repository->findByCode('auth_code_test_001');

        $this->assertNotNull($foundCode);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundCode->id);
        $this->assertSame('auth_code_test_001', $foundCode->code);
        $this->assertSame('client_id_001', $foundCode->clientId);
        $this->assertSame('user_id_001', $foundCode->userId);
        $this->assertSame('https://example.com/callback', $foundCode->redirectUri);
        $this->assertSame(['user:read', 'user:write'], $foundCode->scopes);
        $this->assertNull($foundCode->codeChallenge);
        $this->assertNull($foundCode->codeChallengeMethod);
    }

    public function testFindByCodeNonExistent(): void
    {
        $result = $this->repository->findByCode('non_existent_code');

        $this->assertNull($result);
    }

    public function testCreateWithPKCE(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '223e4567-e89b-12d3-a456-426614174002',
            code: 'auth_code_pkce_001',
            codeChallenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            codeChallengeMethod: 'S256',
        );

        $this->repository->create($authCode);

        $foundCode = $this->repository->findByCode('auth_code_pkce_001');

        $this->assertNotNull($foundCode);
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $foundCode->codeChallenge);
        $this->assertSame('S256', $foundCode->codeChallengeMethod);
    }

    public function testConsumeExistingCode(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '323e4567-e89b-12d3-a456-426614174003',
            code: 'auth_code_consume_001',
        );

        $this->repository->create($authCode);

        // Verify code exists
        $this->assertNotNull($this->repository->findByCode('auth_code_consume_001'));

        // Consume code
        $consumeResult = $this->repository->consume('auth_code_consume_001');

        $this->assertTrue($consumeResult);

        // Verify code no longer exists
        $this->assertNull($this->repository->findByCode('auth_code_consume_001'));
    }

    public function testConsumeNonExistentCode(): void
    {
        $result = $this->repository->consume('non_existent_code');

        $this->assertFalse($result);
    }

    public function testCodeCannotBeReusedAfterConsumption(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '423e4567-e89b-12d3-a456-426614174004',
            code: 'auth_code_single_use_001',
        );

        $this->repository->create($authCode);

        // First consumption should succeed
        $this->assertTrue($this->repository->consume('auth_code_single_use_001'));

        // Second consumption should fail (code already deleted)
        $this->assertFalse($this->repository->consume('auth_code_single_use_001'));
    }

    public function testDeleteExpiredCodes(): void
    {
        // Create expired code (expired 1 hour ago)
        $expiredCode1 = $this->createTestAuthorizationCode(
            id: '523e4567-e89b-12d3-a456-426614174005',
            code: 'expired_code_001',
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        // Create expired code (expired 2 hours ago)
        $expiredCode2 = $this->createTestAuthorizationCode(
            id: '523e4567-e89b-12d3-a456-426614174006',
            code: 'expired_code_002',
            expiresAt: new \DateTimeImmutable('-2 hours'),
        );

        // Create valid code (expires in 1 hour)
        $validCode = $this->createTestAuthorizationCode(
            id: '523e4567-e89b-12d3-a456-426614174007',
            code: 'valid_code_001',
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->repository->create($expiredCode1);
        $this->repository->create($expiredCode2);
        $this->repository->create($validCode);

        // Delete expired codes
        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(2, $deletedCount);

        // Verify expired codes are deleted
        $this->assertNull($this->repository->findByCode('expired_code_001'));
        $this->assertNull($this->repository->findByCode('expired_code_002'));

        // Verify valid code still exists
        $this->assertNotNull($this->repository->findByCode('valid_code_001'));
    }

    public function testDeleteExpiredWithNoExpiredCodes(): void
    {
        $validCode = $this->createTestAuthorizationCode(
            id: '623e4567-e89b-12d3-a456-426614174008',
            code: 'valid_code_002',
            expiresAt: new \DateTimeImmutable('+10 minutes'),
        );

        $this->repository->create($validCode);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(0, $deletedCount);
        $this->assertNotNull($this->repository->findByCode('valid_code_002'));
    }

    public function testEmptyScopes(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '723e4567-e89b-12d3-a456-426614174009',
            code: 'auth_code_empty_scopes',
            scopes: [],
        );

        $this->repository->create($authCode);

        $foundCode = $this->repository->findByCode('auth_code_empty_scopes');

        $this->assertNotNull($foundCode);
        $this->assertSame([], $foundCode->scopes);
    }

    public function testMultipleScopes(): void
    {
        $authCode = $this->createTestAuthorizationCode(
            id: '823e4567-e89b-12d3-a456-426614174010',
            code: 'auth_code_multiple_scopes',
            scopes: ['user:read', 'user:write', 'admin:all', 'api:access'],
        );

        $this->repository->create($authCode);

        $foundCode = $this->repository->findByCode('auth_code_multiple_scopes');

        $this->assertNotNull($foundCode);
        $this->assertSame(['user:read', 'user:write', 'admin:all', 'api:access'], $foundCode->scopes);
    }

    public function testCodeExpirationCheck(): void
    {
        // Create expired code
        $expiredCode = $this->createTestAuthorizationCode(
            id: '923e4567-e89b-12d3-a456-426614174011',
            code: 'expired_check_001',
            expiresAt: new \DateTimeImmutable('-5 minutes'),
        );

        // Create valid code
        $validCode = $this->createTestAuthorizationCode(
            id: '923e4567-e89b-12d3-a456-426614174012',
            code: 'valid_check_001',
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $this->assertTrue($expiredCode->isExpired());
        $this->assertFalse($validCode->isExpired());
    }

    public function testDifferentRedirectUris(): void
    {
        $authCode1 = $this->createTestAuthorizationCode(
            id: 'a23e4567-e89b-12d3-a456-426614174013',
            code: 'auth_code_redirect_001',
            redirectUri: 'https://app1.example.com/callback',
        );

        $authCode2 = $this->createTestAuthorizationCode(
            id: 'a23e4567-e89b-12d3-a456-426614174014',
            code: 'auth_code_redirect_002',
            redirectUri: 'https://app2.example.com/oauth/callback',
        );

        $this->repository->create($authCode1);
        $this->repository->create($authCode2);

        $found1 = $this->repository->findByCode('auth_code_redirect_001');
        $found2 = $this->repository->findByCode('auth_code_redirect_002');

        $this->assertNotNull($found1);
        $this->assertNotNull($found2);
        $this->assertSame('https://app1.example.com/callback', $found1->redirectUri);
        $this->assertSame('https://app2.example.com/oauth/callback', $found2->redirectUri);
    }

    public function testCreatedAtTimestampPreserved(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-15 10:30:00');

        $authCode = $this->createTestAuthorizationCode(
            id: 'b23e4567-e89b-12d3-a456-426614174015',
            code: 'auth_code_timestamp_001',
            createdAt: $createdAt,
        );

        $this->repository->create($authCode);

        $foundCode = $this->repository->findByCode('auth_code_timestamp_001');

        $this->assertNotNull($foundCode);
        $this->assertSame('2025-01-15 10:30:00', $foundCode->createdAt->format('Y-m-d H:i:s'));
    }

    /**
     * @param list<string> $scopes
     */
    private function createTestAuthorizationCode(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $code = 'test_auth_code',
        string $clientId = 'client_id_001',
        string $userId = 'user_id_001',
        string $redirectUri = 'https://example.com/callback',
        array $scopes = ['user:read'],
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $createdAt = null,
    ): OAuthAuthorizationCode {
        return new OAuthAuthorizationCode(
            id: $id,
            code: $code,
            clientId: $clientId,
            userId: $userId,
            redirectUri: $redirectUri,
            scopes: $scopes,
            codeChallenge: $codeChallenge,
            codeChallengeMethod: $codeChallengeMethod,
            expiresAt: $expiresAt ?? new \DateTimeImmutable('+10 minutes'),
            createdAt: $createdAt ?? new \DateTimeImmutable(),
        );
    }
}
