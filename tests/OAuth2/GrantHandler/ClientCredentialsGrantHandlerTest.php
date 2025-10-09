<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\GrantHandler;

use App\Application\AccessToken\DTO\JwtPayloadDTO;
use App\Application\AccessToken\Enum\GrantType;
use App\Application\AccessToken\Exception\InvalidRequestException;
use App\Application\AccessToken\GrantHandler\ClientCredentialsGrantHandler;
use App\Application\AccessToken\Service\JwtTokenGeneratorInterface;
use App\Domain\AccessToken\Event\AccessTokenIssuedEvent;
use App\Domain\OAuthClient\Exception\UnauthorizedClientException;
use App\Domain\Shared\Factory\IdentityFactoryInterface;
use App\Tests\Helper\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ClientCredentialsGrantHandlerTest extends TestCase
{
    private const CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL = 300;

    private ClientCredentialsGrantHandler $handler;
    private JwtTokenGeneratorInterface $jwtTokenGenerator;
    private IdentityFactoryInterface $identityFactory;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);
        $this->identityFactory = $this->createMock(IdentityFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new ClientCredentialsGrantHandler(
            $this->jwtTokenGenerator,
            $this->identityFactory,
            $this->eventDispatcher,
            self::CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL
        );
    }

    public function testSupports(): void
    {
        self::assertTrue($this->handler->supports(GrantType::CLIENT_CREDENTIALS));
        self::assertFalse($this->handler->supports(GrantType::AUTHORIZATION_CODE));
        self::assertFalse($this->handler->supports(GrantType::REFRESH_TOKEN));
    }

    public function testHandleSuccess(): void
    {
        $client = (new ClientBuilder())->confidential()->withScopes(['read', 'write'])->build();

        $this->identityFactory->expects(self::once())
            ->method('generate')
            ->willReturn('test-jti-123');

        $this->jwtTokenGenerator->expects(self::once())
            ->method('generate')
            ->with(self::callback(function (JwtPayloadDTO $payload) use ($client) {
                return $payload->audience === $client->clientId
                    && $payload->subject === $client->clientId
                    && 'read' === $payload->scope
                    && self::CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL === $payload->expiresIn;
            }))
            ->willReturn('generated-jwt');

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (AccessTokenIssuedEvent $event) use ($client) {
                return $event->userId === $client->clientId
                    && $event->clientId === $client->clientId
                    && $event->grantType === GrantType::CLIENT_CREDENTIALS->value
                    && $event->scopes === ['read']
                    && 'test-jti-123' === $event->jti;
            }));

        $response = $this->handler->handle(['scope' => 'read'], $client);

        self::assertSame('generated-jwt', $response->accessToken);
        self::assertSame('Bearer', $response->tokenType);
        self::assertSame(self::CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL, $response->expiresIn);
        self::assertNull($response->refreshToken);
        self::assertSame('read', $response->scope);
    }

    public function testHandlePublicClientFails(): void
    {
        $this->expectException(UnauthorizedClientException::class);

        $client = (new ClientBuilder())->public()->build();

        $this->handler->handle([], $client);
    }

    public function testHandleInvalidScopeParameter(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The "scope" parameter must be a string.');

        $client = (new ClientBuilder())->confidential()->build();

        $this->handler->handle(['scope' => ['not a string']], $client);
    }
}
