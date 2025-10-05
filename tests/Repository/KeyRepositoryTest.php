<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Infrastructure\Persistance\Doctrine\Repository\KeyRepository;
use App\Model\OAuthKey;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for KeyRepository.
 *
 * Tests CRUD operations and key rotation functionality using OAuthKey model.
 */
final class KeyRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private KeyRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new KeyRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindKey(): void
    {
        $key = $this->createTestKey(
            id: '123e4567-e89b-12d3-a456-426614174001',
            kid: 'key-2025-01-001',
            algorithm: 'RS256',
            publicKey: 'PUBLIC_KEY_CONTENT',
            privateKeyEncrypted: 'ENCRYPTED_PRIVATE_KEY',
            isActive: true,
        );

        $this->repository->create($key);

        $foundKey = $this->repository->find('123e4567-e89b-12d3-a456-426614174001');

        $this->assertNotNull($foundKey);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundKey->id);
        $this->assertSame('key-2025-01-001', $foundKey->kid);
        $this->assertSame('RS256', $foundKey->algorithm);
        $this->assertSame('PUBLIC_KEY_CONTENT', $foundKey->publicKey);
        $this->assertSame('ENCRYPTED_PRIVATE_KEY', $foundKey->privateKeyEncrypted);
        $this->assertTrue($foundKey->isActive);
    }

    public function testFindByKid(): void
    {
        $key = $this->createTestKey(
            id: '223e4567-e89b-12d3-a456-426614174002',
            kid: 'key-2025-01-002',
        );

        $this->repository->create($key);

        $foundKey = $this->repository->findByKid('key-2025-01-002');

        $this->assertNotNull($foundKey);
        $this->assertSame('223e4567-e89b-12d3-a456-426614174002', $foundKey->id);
        $this->assertSame('key-2025-01-002', $foundKey->kid);
    }

    public function testFindByKidNonExistent(): void
    {
        $result = $this->repository->findByKid('non-existent-kid');

        $this->assertNull($result);
    }

    public function testFindNonExistentKey(): void
    {
        $result = $this->repository->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    public function testUpdateKey(): void
    {
        $key = $this->createTestKey(
            id: '323e4567-e89b-12d3-a456-426614174003',
            kid: 'key-2025-01-003',
            algorithm: 'RS256',
            isActive: true,
        );

        $this->repository->create($key);

        // Update key
        $updatedKey = new OAuthKey(
            id: '323e4567-e89b-12d3-a456-426614174003',
            kid: 'key-2025-01-003',
            algorithm: 'RS512',
            publicKey: 'UPDATED_PUBLIC_KEY',
            privateKeyEncrypted: 'UPDATED_ENCRYPTED_PRIVATE_KEY',
            isActive: false,
            createdAt: $key->createdAt,
            expiresAt: (new \DateTimeImmutable())->modify('+180 days'),
        );

        $this->repository->update($updatedKey);

        $foundKey = $this->repository->find('323e4567-e89b-12d3-a456-426614174003');

        $this->assertNotNull($foundKey);
        $this->assertSame('RS512', $foundKey->algorithm);
        $this->assertSame('UPDATED_PUBLIC_KEY', $foundKey->publicKey);
        $this->assertFalse($foundKey->isActive);
    }

    public function testFindActiveKeys(): void
    {
        $activeKeys = [
            ['id' => '423e4567-e89b-12d3-a456-426614174004', 'kid' => 'key-active-001', 'isActive' => true],
            ['id' => '423e4567-e89b-12d3-a456-426614174005', 'kid' => 'key-active-002', 'isActive' => true],
            ['id' => '423e4567-e89b-12d3-a456-426614174006', 'kid' => 'key-inactive-001', 'isActive' => false],
        ];

        foreach ($activeKeys as $keyData) {
            $key = $this->createTestKey(
                id: $keyData['id'],
                kid: $keyData['kid'],
                isActive: $keyData['isActive'],
            );
            $this->repository->create($key);
        }

        $foundActiveKeys = $this->repository->findActiveKeys();

        $this->assertCount(2, $foundActiveKeys);

        // Verify all returned keys are active
        foreach ($foundActiveKeys as $key) {
            $this->assertTrue($key->isActive);
        }

        // Verify we have both active keys (order may vary)
        $kids = array_map(fn(OAuthKey $k): string => $k->kid, $foundActiveKeys);
        $this->assertContains('key-active-001', $kids);
        $this->assertContains('key-active-002', $kids);
    }

    public function testFindActiveKeysWithNoActiveKeys(): void
    {
        $key = $this->createTestKey(
            id: '523e4567-e89b-12d3-a456-426614174007',
            kid: 'key-inactive-only',
            isActive: false,
        );
        $this->repository->create($key);

        $activeKeys = $this->repository->findActiveKeys();

        $this->assertSame([], $activeKeys);
    }

    public function testDeactivateKey(): void
    {
        $key = $this->createTestKey(
            id: '623e4567-e89b-12d3-a456-426614174008',
            kid: 'key-to-deactivate',
            isActive: true,
        );

        $this->repository->create($key);

        $this->assertTrue($this->repository->findByKid('key-to-deactivate')?->isActive);

        $deactivateResult = $this->repository->deactivate('key-to-deactivate');

        $this->assertTrue($deactivateResult);

        $foundKey = $this->repository->findByKid('key-to-deactivate');
        $this->assertNotNull($foundKey);
        $this->assertFalse($foundKey->isActive);
    }

    public function testDeactivateNonExistentKey(): void
    {
        $result = $this->repository->deactivate('non-existent-kid');

        $this->assertFalse($result);
    }

    public function testDeleteExpired(): void
    {
        // Create expired key
        $expiredKey = new OAuthKey(
            id: '723e4567-e89b-12d3-a456-426614174009',
            kid: 'key-expired',
            algorithm: 'RS256',
            publicKey: 'PUBLIC_KEY',
            privateKeyEncrypted: 'ENCRYPTED_PRIVATE_KEY',
            isActive: false,
            createdAt: (new \DateTimeImmutable())->modify('-180 days'),
            expiresAt: (new \DateTimeImmutable())->modify('-1 day'),
        );
        $this->repository->create($expiredKey);

        // Create valid key
        $validKey = $this->createTestKey(
            id: '823e4567-e89b-12d3-a456-426614174010',
            kid: 'key-valid',
        );
        $this->repository->create($validKey);

        // Delete expired keys
        $deletedCount = $this->repository->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify expired key is deleted
        $this->assertNull($this->repository->findByKid('key-expired'));

        // Verify valid key remains
        $this->assertNotNull($this->repository->findByKid('key-valid'));
    }

    public function testDeleteExpiredWithNoExpiredKeys(): void
    {
        $key = $this->createTestKey(
            id: '923e4567-e89b-12d3-a456-426614174011',
            kid: 'key-not-expired',
        );
        $this->repository->create($key);

        $deletedCount = $this->repository->deleteExpired();

        $this->assertSame(0, $deletedCount);
    }

    public function testMultipleAlgorithms(): void
    {
        $algorithms = [
            ['id' => 'a23e4567-e89b-12d3-a456-426614174012', 'kid' => 'key-rs256', 'algorithm' => 'RS256'],
            ['id' => 'a23e4567-e89b-12d3-a456-426614174013', 'kid' => 'key-rs384', 'algorithm' => 'RS384'],
            ['id' => 'a23e4567-e89b-12d3-a456-426614174014', 'kid' => 'key-rs512', 'algorithm' => 'RS512'],
            ['id' => 'a23e4567-e89b-12d3-a456-426614174015', 'kid' => 'key-es256', 'algorithm' => 'ES256'],
        ];

        foreach ($algorithms as $algData) {
            $key = $this->createTestKey(
                id: $algData['id'],
                kid: $algData['kid'],
                algorithm: $algData['algorithm'],
            );
            $this->repository->create($key);
        }

        $rs256Key = $this->repository->findByKid('key-rs256');
        $es256Key = $this->repository->findByKid('key-es256');

        $this->assertNotNull($rs256Key);
        $this->assertSame('RS256', $rs256Key->algorithm);

        $this->assertNotNull($es256Key);
        $this->assertSame('ES256', $es256Key->algorithm);
    }

    public function testKeyRotationScenario(): void
    {
        // Step 1: Create initial active key
        $oldKey = $this->createTestKey(
            id: 'b23e4567-e89b-12d3-a456-426614174016',
            kid: 'key-2025-01-old',
            isActive: true,
        );
        $this->repository->create($oldKey);

        // Step 2: Create new key and activate it
        $newKey = $this->createTestKey(
            id: 'b23e4567-e89b-12d3-a456-426614174017',
            kid: 'key-2025-02-new',
            isActive: true,
        );
        $this->repository->create($newKey);

        // Step 3: Deactivate old key
        $this->repository->deactivate('key-2025-01-old');

        // Verify only new key is active
        $activeKeys = $this->repository->findActiveKeys();
        $this->assertCount(1, $activeKeys);
        $this->assertSame('key-2025-02-new', $activeKeys[0]->kid);

        // Verify old key still exists but is inactive
        $oldKeyFound = $this->repository->findByKid('key-2025-01-old');
        $this->assertNotNull($oldKeyFound);
        $this->assertFalse($oldKeyFound->isActive);
    }

    public function testUniqueKidConstraint(): void
    {
        $key1 = $this->createTestKey(
            id: 'c23e4567-e89b-12d3-a456-426614174018',
            kid: 'duplicate-kid',
        );
        $this->repository->create($key1);

        $key2 = $this->createTestKey(
            id: 'c23e4567-e89b-12d3-a456-426614174019',
            kid: 'duplicate-kid',
        );

        // Database has unique constraint on kid column
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create OAuth2 key');

        $this->repository->create($key2);
    }

    private function createTestKey(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $kid = 'test-key-id',
        string $algorithm = 'RS256',
        string $publicKey = 'TEST_PUBLIC_KEY',
        string $privateKeyEncrypted = 'TEST_ENCRYPTED_PRIVATE_KEY',
        bool $isActive = false,
    ): OAuthKey {
        return new OAuthKey(
            id: $id,
            kid: $kid,
            algorithm: $algorithm,
            publicKey: $publicKey,
            privateKeyEncrypted: $privateKeyEncrypted,
            isActive: $isActive,
            createdAt: new \DateTimeImmutable(),
            expiresAt: (new \DateTimeImmutable())->modify('+90 days'),
        );
    }
}
