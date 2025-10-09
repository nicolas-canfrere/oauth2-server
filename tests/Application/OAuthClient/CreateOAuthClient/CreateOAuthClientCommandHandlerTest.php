<?php

declare(strict_types=1);

namespace App\Tests\Application\OAuthClient\CreateOAuthClient;

use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommand;
use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommandHandler;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\OAuthClient\Repository\ClientRepositoryInterface;
use App\Domain\OAuthClient\Service\ClientSecretGeneratorInterface;
use App\Domain\Shared\Factory\IdentityFactoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommandHandler
 */
final class CreateOAuthClientCommandHandlerTest extends TestCase
{
    private ClientRepositoryInterface&MockObject $clientRepository;
    private ClientSecretGeneratorInterface&MockObject $secretGenerator;
    private IdentityFactoryInterface&MockObject $identityFactory;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private CreateOAuthClientCommandHandler $handler;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->secretGenerator = $this->createMock(ClientSecretGeneratorInterface::class);
        $this->identityFactory = $this->createMock(IdentityFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->identityFactory->method('generate')
            ->willReturnOnConsecutiveCalls(
                '550e8400-e29b-41d4-a716-446655440000',
                '550e8400-e29b-41d4-a716-446655440001'
            );

        $this->handler = new CreateOAuthClientCommandHandler(
            $this->clientRepository,
            $this->secretGenerator,
            $this->identityFactory,
            $this->eventDispatcher,
        );
    }

    public function testCreatesConfidentialClientAndReturnsGeneratedSecret(): void
    {
        $command = new CreateOAuthClientCommand(
            name: 'My App',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code', 'refresh_token'],
            scopes: ['openid', 'profile'],
            isConfidential: true,
            pkceRequired: true,
        );

        $generatedSecret = 'generated-secret';

        $this->secretGenerator->expects(self::once())
            ->method('generate')
            ->willReturn($generatedSecret);

        $this->secretGenerator->expects(self::once())
            ->method('validate')
            ->with($generatedSecret);

        $this->clientRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthClient $client) use ($command, $generatedSecret): bool {
                $this->assertSame($command->name, $client->name);
                $this->assertSame($command->redirectUri, $client->redirectUri);
                $this->assertSame($command->grantTypes, $client->grantTypes);
                $this->assertSame($command->scopes, $client->scopes);
                $this->assertTrue($client->isConfidential);
                $this->assertTrue($client->pkceRequired);
                $this->assertNotEmpty($client->id);
                $this->assertNotEmpty($client->clientId);
                $this->assertNotNull($client->clientSecretHash);
                $this->assertTrue(password_verify($generatedSecret, (string) $client->clientSecretHash));

                return true;
            }));

        $result = ($this->handler)($command);

        self::assertSame($generatedSecret, $result['client_secret']);
        self::assertNotEmpty($result['client_id']);
    }

    public function testUsesProvidedSecretWhenGiven(): void
    {
        $command = new CreateOAuthClientCommand(
            name: 'CLI App',
            redirectUri: 'https://example.org/cli',
            grantTypes: ['client_credentials'],
            scopes: ['api'],
            isConfidential: true,
            pkceRequired: false,
            clientSecret: 'provided-secret',
        );

        $this->secretGenerator->expects(self::never())
            ->method('generate');

        $this->secretGenerator->expects(self::once())
            ->method('validate')
            ->with('provided-secret');

        $this->clientRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthClient $client) use ($command): bool {
                return password_verify('provided-secret', (string) $client->clientSecretHash)
                    && $client->pkceRequired === $command->pkceRequired;
            }));

        $result = ($this->handler)($command);

        self::assertSame('provided-secret', $result['client_secret']);
    }

    public function testCreatesPublicClientWithoutSecret(): void
    {
        $command = new CreateOAuthClientCommand(
            name: 'SPA',
            redirectUri: 'https://spa.example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['openid'],
            isConfidential: false,
            pkceRequired: true,
        );

        $this->secretGenerator->expects(self::never())
            ->method('generate');
        $this->secretGenerator->expects(self::never())
            ->method('validate');

        $this->clientRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthClient $client): bool {
                return false === $client->isConfidential
                    && true === $client->pkceRequired
                    && null === $client->clientSecretHash;
            }));

        $result = ($this->handler)($command);

        self::assertNull($result['client_secret']);
        self::assertNotEmpty($result['client_id']);
    }

    public function testUsesProvidedClientIdWhenSpecified(): void
    {
        $command = new CreateOAuthClientCommand(
            name: 'Legacy Integration',
            redirectUri: 'https://legacy.example.com/redirect',
            grantTypes: ['client_credentials'],
            scopes: ['legacy'],
            isConfidential: false,
            pkceRequired: false,
            clientId: 'custom-client-id',
        );

        $this->clientRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthClient $client): bool {
                return 'custom-client-id' === $client->clientId;
            }));

        $result = ($this->handler)($command);

        self::assertSame('custom-client-id', $result['client_id']);
    }

    public function testPropagatesRepositoryExceptions(): void
    {
        $command = new CreateOAuthClientCommand(
            name: 'My App',
            redirectUri: 'https://example.com/callback',
            grantTypes: ['authorization_code'],
            scopes: ['openid'],
            isConfidential: false,
            pkceRequired: true,
        );

        $this->clientRepository->method('create')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($this->handler)($command);
    }
}
