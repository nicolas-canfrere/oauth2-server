<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Model\OAuthClient;
use App\Repository\ClientRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for ClientRepository.
 *
 * Tests CRUD operations using OAuthClient model.
 */
final class ClientRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private ClientRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new ClientRepository($this->connection);

        // Clean the table before each test
        $this->connection->executeStatement('TRUNCATE TABLE oauth_clients RESTART IDENTITY CASCADE');
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->connection->executeStatement('TRUNCATE TABLE oauth_clients RESTART IDENTITY CASCADE');

        // Shutdown kernel
        self::ensureKernelShutdown();
    }

    public function testSaveAndFindClient(): void
    {
        $client = $this->createTestClient(
            id: '123e4567-e89b-12d3-a456-426614174001',
            clientId: 'test_client_001',
            name: 'Test Application',
        );

        $this->repository->save($client);

        $foundClient = $this->repository->find('123e4567-e89b-12d3-a456-426614174001');

        $this->assertNotNull($foundClient);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundClient->id);
        $this->assertSame('test_client_001', $foundClient->clientId);
        $this->assertSame('Test Application', $foundClient->name);
        $this->assertSame(['authorization_code'], $foundClient->grantTypes);
        $this->assertSame(['user:read'], $foundClient->scopes);
        $this->assertTrue($foundClient->isConfidential);
        $this->assertFalse($foundClient->pkceRequired);
    }

    public function testFindNonExistentClient(): void
    {
        $result = $this->repository->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    public function testUpdateClient(): void
    {
        $client = $this->createTestClient(
            id: '223e4567-e89b-12d3-a456-426614174002',
            clientId: 'update_test_client',
            name: 'Original Name',
            scopes: ['read'],
        );

        $this->repository->save($client);

        // Create updated version
        $updatedClient = new OAuthClient(
            id: '223e4567-e89b-12d3-a456-426614174002',
            clientId: 'update_test_client',
            clientSecretHash: $client->clientSecretHash,
            name: 'Updated Name',
            redirectUri: $client->redirectUri,
            grantTypes: $client->grantTypes,
            scopes: ['read', 'write', 'admin'],
            isConfidential: $client->isConfidential,
            pkceRequired: true,
            createdAt: $client->createdAt,
        );

        $this->repository->save($updatedClient);

        $foundClient = $this->repository->find('223e4567-e89b-12d3-a456-426614174002');

        $this->assertNotNull($foundClient);
        $this->assertSame('Updated Name', $foundClient->name);
        $this->assertSame(['read', 'write', 'admin'], $foundClient->scopes);
        $this->assertTrue($foundClient->pkceRequired);
    }

    public function testDeleteClient(): void
    {
        $client = $this->createTestClient(
            id: '323e4567-e89b-12d3-a456-426614174003',
            clientId: 'delete_test_client',
            name: 'To Be Deleted',
        );

        $this->repository->save($client);

        $this->assertNotNull($this->repository->find('323e4567-e89b-12d3-a456-426614174003'));

        $deleteResult = $this->repository->delete('323e4567-e89b-12d3-a456-426614174003');

        $this->assertTrue($deleteResult);
        $this->assertNull($this->repository->find('323e4567-e89b-12d3-a456-426614174003'));
    }

    public function testDeleteNonExistentClient(): void
    {
        $result = $this->repository->delete('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($result);
    }

    public function testFindAll(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $client = $this->createTestClient(
                id: sprintf('423e4567-e89b-12d3-a456-42661417400%d', $i),
                clientId: "client_{$i}",
                name: "Client {$i}",
            );
            $this->repository->save($client);
        }

        $allClients = $this->repository->findAll();

        $this->assertCount(5, $allClients);
    }

    public function testFindAllWithPagination(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $client = $this->createTestClient(
                id: sprintf('523e4567-e89b-12d3-a456-42661417%04d', $i),
                clientId: "paginated_client_{$i}",
                name: "Paginated Client {$i}",
            );
            $this->repository->save($client);
        }

        $firstPage = $this->repository->findAll(5, 0);
        $this->assertCount(5, $firstPage);

        $secondPage = $this->repository->findAll(5, 5);
        $this->assertCount(5, $secondPage);

        $this->assertNotSame($firstPage[0]->id, $secondPage[0]->id);
    }

    public function testComplexGrantTypesAndScopes(): void
    {
        $client = $this->createTestClient(
            id: '623e4567-e89b-12d3-a456-426614174006',
            clientId: 'complex_test_client',
            name: 'Complex Test Client',
            grantTypes: ['authorization_code', 'refresh_token', 'client_credentials'],
            scopes: ['user:read', 'user:write', 'admin:all', 'api:access'],
            isConfidential: true,
            pkceRequired: true,
        );

        $this->repository->save($client);

        $retrievedClient = $this->repository->find('623e4567-e89b-12d3-a456-426614174006');

        $this->assertNotNull($retrievedClient);
        $this->assertSame(['authorization_code', 'refresh_token', 'client_credentials'], $retrievedClient->grantTypes);
        $this->assertSame(['user:read', 'user:write', 'admin:all', 'api:access'], $retrievedClient->scopes);
        $this->assertTrue($retrievedClient->isConfidential);
        $this->assertTrue($retrievedClient->pkceRequired);
    }

    public function testEmptyGrantTypesAndScopes(): void
    {
        $client = $this->createTestClient(
            id: '723e4567-e89b-12d3-a456-426614174007',
            clientId: 'empty_arrays_client',
            name: 'Empty Arrays Client',
            grantTypes: [],
            scopes: [],
        );

        $this->repository->save($client);

        $retrievedClient = $this->repository->find('723e4567-e89b-12d3-a456-426614174007');

        $this->assertNotNull($retrievedClient);
        $this->assertSame([], $retrievedClient->grantTypes);
        $this->assertSame([], $retrievedClient->scopes);
    }

    /**
     * @param list<string> $grantTypes
     * @param list<string> $scopes
     */
    private function createTestClient(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $clientId = 'test_client',
        string $name = 'Test Application',
        array $grantTypes = ['authorization_code'],
        array $scopes = ['user:read'],
        bool $isConfidential = true,
        bool $pkceRequired = false,
    ): OAuthClient {
        return new OAuthClient(
            id: $id,
            clientId: $clientId,
            clientSecretHash: password_hash('secret123', PASSWORD_BCRYPT) ?: '',
            name: $name,
            redirectUri: 'https://example.com/callback',
            grantTypes: $grantTypes,
            scopes: $scopes,
            isConfidential: $isConfidential,
            pkceRequired: $pkceRequired,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
