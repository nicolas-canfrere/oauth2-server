<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Domain\Scope\Model\OAuthScope;
use App\Infrastructure\Persistance\Doctrine\Repository\ScopeRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for ScopeRepository.
 *
 * Tests CRUD operations and scope-specific queries using OAuthScope model.
 */
final class ScopeRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private ScopeRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new ScopeRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindScope(): void
    {
        $scope = $this->createTestScope(
            id: '123e4567-e89b-12d3-a456-426614174001',
            scope: 'user:read',
            description: 'Read user profile information',
            isDefault: true,
        );

        $this->repository->create($scope);

        $foundScope = $this->repository->find('123e4567-e89b-12d3-a456-426614174001');

        $this->assertNotNull($foundScope);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundScope->id);
        $this->assertSame('user:read', $foundScope->scope);
        $this->assertSame('Read user profile information', $foundScope->description);
        $this->assertTrue($foundScope->isDefault);
    }

    public function testFindNonExistentScope(): void
    {
        $result = $this->repository->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    public function testUpdateScope(): void
    {
        $scope = $this->createTestScope(
            id: '223e4567-e89b-12d3-a456-426614174002',
            scope: 'user:write',
            description: 'Original description',
            isDefault: false,
        );

        $this->repository->create($scope);

        // Create updated version
        $updatedScope = new OAuthScope(
            id: '223e4567-e89b-12d3-a456-426614174002',
            scope: 'user:write',
            description: 'Updated description for write access',
            isDefault: true,
            createdAt: $scope->createdAt,
        );

        $this->repository->update($updatedScope);

        $foundScope = $this->repository->find('223e4567-e89b-12d3-a456-426614174002');

        $this->assertNotNull($foundScope);
        $this->assertSame('Updated description for write access', $foundScope->description);
        $this->assertTrue($foundScope->isDefault);
    }

    public function testFindAll(): void
    {
        $scopes = [
            ['scope' => 'user:read', 'description' => 'Read user data'],
            ['scope' => 'user:write', 'description' => 'Write user data'],
            ['scope' => 'admin:all', 'description' => 'Admin access'],
            ['scope' => 'api:access', 'description' => 'API access'],
        ];

        foreach ($scopes as $index => $scopeData) {
            $scope = $this->createTestScope(
                id: sprintf('323e4567-e89b-12d3-a456-42661417400%d', $index + 1),
                scope: $scopeData['scope'],
                description: $scopeData['description'],
            );
            $this->repository->create($scope);
        }

        $allScopes = $this->repository->findAll();

        $this->assertCount(4, $allScopes);

        // Verify alphabetical ordering by scope
        $scopeNames = array_map(fn(OAuthScope $s): string => $s->scope, $allScopes);
        $this->assertSame(['admin:all', 'api:access', 'user:read', 'user:write'], $scopeNames);
    }

    public function testFindByScopes(): void
    {
        $scopes = [
            ['id' => '423e4567-e89b-12d3-a456-426614174001', 'scope' => 'user:read', 'description' => 'Read user'],
            ['id' => '423e4567-e89b-12d3-a456-426614174002', 'scope' => 'user:write', 'description' => 'Write user'],
            ['id' => '423e4567-e89b-12d3-a456-426614174003', 'scope' => 'admin:all', 'description' => 'Admin'],
            ['id' => '423e4567-e89b-12d3-a456-426614174004', 'scope' => 'api:access', 'description' => 'API'],
        ];

        foreach ($scopes as $scopeData) {
            $scope = $this->createTestScope(
                id: $scopeData['id'],
                scope: $scopeData['scope'],
                description: $scopeData['description'],
            );
            $this->repository->create($scope);
        }

        // Find specific scopes
        $foundScopes = $this->repository->findByScopes(['user:read', 'admin:all']);

        $this->assertCount(2, $foundScopes);

        $scopeNames = array_map(fn(OAuthScope $s): string => $s->scope, $foundScopes);
        $this->assertContains('user:read', $scopeNames);
        $this->assertContains('admin:all', $scopeNames);
    }

    public function testFindByScopesWithEmptyArray(): void
    {
        $foundScopes = $this->repository->findByScopes([]);

        $this->assertSame([], $foundScopes);
    }

    public function testFindByScopesNonExistent(): void
    {
        $scope = $this->createTestScope(
            id: '523e4567-e89b-12d3-a456-426614174001',
            scope: 'existing:scope',
            description: 'Existing scope',
        );
        $this->repository->create($scope);

        $foundScopes = $this->repository->findByScopes(['non:existent', 'also:missing']);

        $this->assertSame([], $foundScopes);
    }

    public function testGetDefaults(): void
    {
        $scopes = [
            ['id' => '623e4567-e89b-12d3-a456-426614174001', 'scope' => 'user:read', 'isDefault' => true],
            ['id' => '623e4567-e89b-12d3-a456-426614174002', 'scope' => 'user:write', 'isDefault' => false],
            ['id' => '623e4567-e89b-12d3-a456-426614174003', 'scope' => 'profile:read', 'isDefault' => true],
            ['id' => '623e4567-e89b-12d3-a456-426614174004', 'scope' => 'admin:all', 'isDefault' => false],
        ];

        foreach ($scopes as $scopeData) {
            $scope = $this->createTestScope(
                id: $scopeData['id'],
                scope: $scopeData['scope'],
                description: "Description for {$scopeData['scope']}",
                isDefault: $scopeData['isDefault'],
            );
            $this->repository->create($scope);
        }

        $defaultScopes = $this->repository->getDefaults();

        $this->assertCount(2, $defaultScopes);

        $scopeNames = array_map(fn(OAuthScope $s): string => $s->scope, $defaultScopes);
        $this->assertContains('user:read', $scopeNames);
        $this->assertContains('profile:read', $scopeNames);

        // Verify all returned scopes are defaults
        foreach ($defaultScopes as $scope) {
            $this->assertTrue($scope->isDefault);
        }
    }

    public function testGetDefaultsWhenNoneExist(): void
    {
        $scope = $this->createTestScope(
            id: '723e4567-e89b-12d3-a456-426614174001',
            scope: 'non:default',
            description: 'Non-default scope',
            isDefault: false,
        );
        $this->repository->create($scope);

        $defaultScopes = $this->repository->getDefaults();

        $this->assertSame([], $defaultScopes);
    }

    public function testDuplicateScopeNameThrowsException(): void
    {
        // First scope
        $scope1 = $this->createTestScope(
            id: '823e4567-e89b-12d3-a456-426614174001',
            scope: 'duplicate:test',
            description: 'First instance',
            isDefault: true,
        );
        $this->repository->create($scope1);

        // Attempt to save different scope with same name (different ID)
        $scope2 = $this->createTestScope(
            id: '823e4567-e89b-12d3-a456-426614174002',
            scope: 'duplicate:test',
            description: 'Second instance',
            isDefault: false,
        );

        // Database has unique constraint on 'scope' column
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create OAuth2 scope');

        $this->repository->create($scope2);
    }

    private function createTestScope(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $scope = 'test:scope',
        string $description = 'Test scope description',
        bool $isDefault = false,
    ): OAuthScope {
        return new OAuthScope(
            id: $id,
            scope: $scope,
            description: $description,
            isDefault: $isDefault,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
