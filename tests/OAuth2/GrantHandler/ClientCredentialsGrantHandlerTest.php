<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\GrantHandler;

use App\OAuth2\DTO\JwtPayloadDTO;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\UnauthorizedClientException;
use App\OAuth2\GrantHandler\ClientCredentialsGrantHandler;
use App\OAuth2\GrantType;
use App\OAuth2\Service\JwtTokenGeneratorInterface;
use App\Tests\Helper\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ClientCredentialsGrantHandlerTest extends TestCase
{
    private const CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL = 300;

    private ClientCredentialsGrantHandler $handler;
    private JwtTokenGeneratorInterface $jwtTokenGenerator;

    protected function setUp(): void
    {
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);

        $this->handler = new ClientCredentialsGrantHandler(
            $this->jwtTokenGenerator,
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

        $this->jwtTokenGenerator->expects(self::once())
            ->method('generate')
            ->with(self::callback(function (JwtPayloadDTO $payload) use ($client) {
                return $payload->audience === $client->clientId
                    && $payload->subject === $client->clientId
                    && 'read' === $payload->scope
                    && self::CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL === $payload->expiresIn;
            }))
            ->willReturn('generated-jwt');

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
