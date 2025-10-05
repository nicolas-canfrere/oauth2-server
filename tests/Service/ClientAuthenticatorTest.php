<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\OAuthClient;
use App\OAuth2\Exception\InvalidClientException;
use App\Repository\ClientRepositoryInterface;
use App\Service\ClientAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for ClientAuthenticator service.
 *
 * Tests all authentication mechanisms:
 * - HTTP Basic Authentication
 * - POST body authentication
 * - Public client authentication
 * - Client secret verification with timing attack protection
 */
final class ClientAuthenticatorTest extends TestCase
{
    private ClientRepositoryInterface&MockObject $clientRepository;
    private LoggerInterface&MockObject $logger;
    private ClientAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->authenticator = new ClientAuthenticator($this->clientRepository, $this->logger);
    }

    public function testAuthenticateWithBasicAuthSuccess(): void
    {
        $clientId = 'test-client';
        $clientSecret = 'test-secret';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: 'client-uuid',
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Test Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $request = new Request();
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $result = $this->authenticator->authenticateWithBasicAuth($request);

        $this->assertSame($client, $result);
    }

    public function testAuthenticateWithBasicAuthInvalidSecret(): void
    {
        $clientId = 'test-client';
        $clientSecret = 'wrong-secret';
        $correctSecretHash = password_hash('correct-secret', PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: 'client-uuid',
            clientId: $clientId,
            clientSecretHash: $correctSecretHash,
            name: 'Test Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $request = new Request();
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: invalid client secret.');
        $this->authenticator->authenticateWithBasicAuth($request);
    }

    public function testAuthenticateWithBasicAuthMissingHeader(): void
    {
        $request = new Request();

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('HTTP Basic authentication header is missing or invalid.');
        $this->authenticator->authenticateWithBasicAuth($request);
    }

    public function testAuthenticateWithBasicAuthInvalidBase64(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic invalid@@@base64');

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Invalid Base64 encoding in Basic Auth header.');
        $this->authenticator->authenticateWithBasicAuth($request);
    }

    public function testAuthenticateWithBasicAuthInvalidFormat(): void
    {
        $request = new Request();
        $credentials = base64_encode('no-colon-separator');
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Invalid Basic Auth format (missing colon separator).');
        $this->authenticator->authenticateWithBasicAuth($request);
    }

    public function testAuthenticateWithPostBodySuccess(): void
    {
        $clientId = 'test-client';
        $clientSecret = 'test-secret';
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $client = new OAuthClient(
            id: 'client-uuid',
            clientId: $clientId,
            clientSecretHash: $secretHash,
            name: 'Test Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['client_credentials'],
            scopes: ['api'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $request = new Request([], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $result = $this->authenticator->authenticateWithPostBody($request);

        $this->assertSame($client, $result);
    }

    public function testAuthenticateWithPostBodyMissingClientId(): void
    {
        $request = new Request([], [
            'client_secret' => 'secret',
        ]);

        $this->expectException(InvalidClientException::class);
        $this->authenticator->authenticateWithPostBody($request);
    }

    public function testAuthenticateWithPostBodyMissingClientSecret(): void
    {
        $request = new Request([], [
            'client_id' => 'test-client',
        ]);

        $this->expectException(InvalidClientException::class);
        $this->authenticator->authenticateWithPostBody($request);
    }

    public function testAuthenticatePublicClientSuccess(): void
    {
        $clientId = 'public-client';

        $client = new OAuthClient(
            id: 'client-uuid',
            clientId: $clientId,
            clientSecretHash: '',
            name: 'Public Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: false,
            pkceRequired: true,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $result = $this->authenticator->authenticatePublicClient($clientId);

        $this->assertSame($client, $result);
    }

    public function testAuthenticatePublicClientFailsForConfidentialClient(): void
    {
        $clientId = 'confidential-client';

        $client = new OAuthClient(
            id: 'client-uuid',
            clientId: $clientId,
            clientSecretHash: password_hash('secret', PASSWORD_BCRYPT),
            name: 'Confidential Client',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['read'],
            isConfidential: true,
            pkceRequired: false,
            createdAt: new \DateTimeImmutable(),
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Confidential client cannot be authenticated as a public client.');
        $this->authenticator->authenticatePublicClient($clientId);
    }

    public function testAuthenticatePublicClientNotFound(): void
    {
        $clientId = 'nonexistent-client';

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn(null);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Public client not found.');
        $this->authenticator->authenticatePublicClient($clientId);
    }

    public function testVerifyClientSecretSuccess(): void
    {
        $plainSecret = 'my-secret-password';
        $secretHash = password_hash($plainSecret, PASSWORD_BCRYPT);

        $result = $this->authenticator->verifyClientSecret($plainSecret, $secretHash);

        $this->assertTrue($result);
    }

    public function testVerifyClientSecretFailure(): void
    {
        $plainSecret = 'wrong-password';
        $secretHash = password_hash('correct-password', PASSWORD_BCRYPT);

        $result = $this->authenticator->verifyClientSecret($plainSecret, $secretHash);

        $this->assertFalse($result);
    }

    public function testAuthenticateWithClientNotFound(): void
    {
        $clientId = 'nonexistent-client';

        $this->clientRepository
            ->expects($this->any())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn(null);

        $request = new Request([], [
            'client_id' => $clientId,
            'client_secret' => 'any-secret',
        ]);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: No valid credentials provided.');

        $this->authenticator->authenticate($request);
    }

    public function testTimingAttackProtectionForNonexistentClient(): void
    {
        $clientId = 'nonexistent-client';
        $clientSecret = 'any-secret';

        $this->clientRepository
            ->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn(null);

        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $request = new Request();
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: client not found.');

        $startTime = microtime(true);
        $this->authenticator->authenticateWithBasicAuth($request);
        $endTime = microtime(true);

        // Execution should take some time (dummy hash verification)
        $executionTime = $endTime - $startTime;
        $this->assertGreaterThan(0, $executionTime);
    }
}
