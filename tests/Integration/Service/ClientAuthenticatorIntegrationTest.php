<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Infrastructure\Persistance\Doctrine\Repository\ClientRepository;
use App\Model\OAuthClient;
use App\OAuth2\Exception\InvalidClientException;
use App\Service\ClientAuthenticator;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Integration tests for ClientAuthenticator with real database.
 *
 * Tests authentication scenarios with actual database operations
 * to ensure proper integration between components.
 */
final class ClientAuthenticatorIntegrationTest extends KernelTestCase
{
    private Connection $connection;
    private ClientRepository $clientRepository;
    private ClientAuthenticator $authenticator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->clientRepository = new ClientRepository($this->connection);
        $this->authenticator = new ClientAuthenticator($this->clientRepository, new NullLogger());

        // Clean up test data
        $this->connection->executeStatement('DELETE FROM oauth_clients');
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->connection->executeStatement('DELETE FROM oauth_clients');
        self::ensureKernelShutdown();
    }

    public function testAuthenticateConfidentialClientWithBasicAuth(): void
    {
        // Create confidential client
        $clientId = 'confidential-client-' . uniqid();
        $clientSecret = 'super-secret-password';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Test HTTP Basic Authentication
        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $request = new Request();
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $authenticatedClient = $this->authenticator->authenticate($request);

        $this->assertSame($clientId, $authenticatedClient->clientId);
        $this->assertTrue($authenticatedClient->isConfidential);
    }

    public function testAuthenticateConfidentialClientWithPostBody(): void
    {
        // Create confidential client
        $clientId = 'confidential-client-' . uniqid();
        $clientSecret = 'super-secret-password';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['client_credentials'],
            scopes: ['api'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Test POST body authentication
        $request = new Request([], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $authenticatedClient = $this->authenticator->authenticate($request);

        $this->assertSame($clientId, $authenticatedClient->clientId);
    }

    public function testAuthenticatePublicClient(): void
    {
        // Create public client (no secret)
        $clientId = 'public-client-' . uniqid();

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: '',
            name: 'Public Client (Mobile App)',
            redirectUri: 'myapp://callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: false,
            pkceRequired: true,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Test public client authentication (client_id only)
        $request = new Request([], [
            'client_id' => $clientId,
        ]);

        $authenticatedClient = $this->authenticator->authenticate($request);

        $this->assertSame($clientId, $authenticatedClient->clientId);
        $this->assertFalse($authenticatedClient->isConfidential);
        $this->assertTrue($authenticatedClient->pkceRequired);
    }

    public function testAuthenticationFailsWithInvalidSecret(): void
    {
        // Create confidential client
        $clientId = 'confidential-client-' . uniqid();
        $clientSecret = 'correct-secret';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Try with wrong secret
        $wrongCredentials = base64_encode($clientId . ':wrong-secret');
        $request = new Request();
        $request->headers->set('Authorization', 'Basic ' . $wrongCredentials);

        $this->expectException(InvalidClientException::class);
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticationFailsForNonexistentClient(): void
    {
        $request = new Request([], [
            'client_id' => 'nonexistent-client',
            'client_secret' => 'any-secret',
        ]);

        $this->expectException(InvalidClientException::class);
        $this->authenticator->authenticate($request);
    }

    public function testPublicClientAuthenticationFailsForConfidentialClient(): void
    {
        // Create confidential client
        $clientId = 'confidential-client-' . uniqid();
        $secretHash = password_hash('secret', PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Try public client authentication (should fail for confidential client)
        $this->expectException(InvalidClientException::class);
        $this->authenticator->authenticatePublicClient($clientId);
    }

    public function testMultipleAuthenticationMethodsPriority(): void
    {
        // Create confidential client
        $clientId = 'confidential-client-' . uniqid();
        $clientSecret = 'super-secret-password';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: Uuid::v4()->toString(),
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository->create($client);

        // Request with both Basic Auth and POST body (Basic Auth should take priority)
        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $request = new Request([], [
            'client_id' => 'different-client',
            'client_secret' => 'different-secret',
        ]);
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $authenticatedClient = $this->authenticator->authenticate($request);

        $this->assertSame($clientId, $authenticatedClient->clientId);
    }
}
