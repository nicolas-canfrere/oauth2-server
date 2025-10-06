<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\GrantHandler;

use App\Application\AccessToken\DTO\TokenResponseDTO;
use App\Application\AccessToken\Enum\GrantType;
use App\Application\AccessToken\Exception\InvalidRequestException;
use App\Application\AccessToken\GrantHandler\GrantHandlerInterface;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\OAuth2\Exception\InvalidGrantException;
use App\Tests\Helper\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class GrantHandlerInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        self::assertTrue(interface_exists(GrantHandlerInterface::class));
    }

    public function testInterfaceHasSupportsMethod(): void
    {
        $reflection = new \ReflectionClass(GrantHandlerInterface::class);
        self::assertTrue($reflection->hasMethod('supports'));
        $method = $reflection->getMethod('supports');
        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        $parameters = $method->getParameters();
        self::assertSame('grantType', $parameters[0]->getName());
        self::assertTrue($parameters[0]->hasType());
        $paramType = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $paramType);
        self::assertSame(GrantType::class, $paramType->getName());
        self::assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }

    public function testInterfaceHasHandleMethod(): void
    {
        $reflection = new \ReflectionClass(GrantHandlerInterface::class);
        self::assertTrue($reflection->hasMethod('handle'));
        $method = $reflection->getMethod('handle');
        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());
        $parameters = $method->getParameters();
        self::assertSame('parameters', $parameters[0]->getName());
        self::assertTrue($parameters[0]->hasType());
        $paramType = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $paramType);
        self::assertSame('array', $paramType->getName());
        self::assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertSame(TokenResponseDTO::class, $returnType->getName());
    }

    public function testConcreteImplementationSupports(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return GrantType::CLIENT_CREDENTIALS === $grantType;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                return new TokenResponseDTO('test_token', 'Bearer', 3600);
            }
        };

        self::assertTrue($handler->supports(GrantType::CLIENT_CREDENTIALS));
        self::assertFalse($handler->supports(GrantType::AUTHORIZATION_CODE));
    }

    public function testConcreteImplementationHandle(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                return new TokenResponseDTO(
                    'generated_access_token',
                    'Bearer',
                    3600,
                    'generated_refresh_token',
                    'user:read'
                );
            }
        };

        $client = (new ClientBuilder())->build();
        $result = $handler->handle([
            'grant_type' => 'test_grant',
            'client_id' => 'test_client',
        ], $client);

        self::assertSame('generated_access_token', $result->accessToken);
        self::assertSame('Bearer', $result->tokenType);
        self::assertSame(3600, $result->expiresIn);
        self::assertSame('generated_refresh_token', $result->refreshToken);
        self::assertSame('user:read', $result->scope);
    }

    public function testConcreteImplementationCanThrowInvalidRequestException(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                if (!isset($parameters['client_id'])) {
                    throw new InvalidRequestException('Missing required parameter: client_id');
                }

                return new TokenResponseDTO('test_token', 'Bearer', 3600);
            }
        };

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing required parameter: client_id');

        $client = (new ClientBuilder())->build();
        $handler->handle([], $client);
    }

    public function testConcreteImplementationCanThrowInvalidGrantException(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                if (($parameters['code'] ?? null) === 'invalid_code') {
                    throw new InvalidGrantException('The authorization code is invalid or expired');
                }

                return new TokenResponseDTO('test_token', 'Bearer', 3600);
            }
        };

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('The authorization code is invalid or expired');

        $client = (new ClientBuilder())->build();
        $handler->handle(['code' => 'invalid_code'], $client);
    }

    public function testMultipleImplementationsWithDifferentGrantTypes(): void
    {
        $authCodeHandler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return GrantType::AUTHORIZATION_CODE === $grantType;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                return new TokenResponseDTO('auth_code_token', 'Bearer', 3600);
            }
        };

        $clientCredentialsHandler = new class implements GrantHandlerInterface {
            public function supports(GrantType $grantType): bool
            {
                return GrantType::CLIENT_CREDENTIALS === $grantType;
            }

            public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
            {
                return new TokenResponseDTO('client_credentials_token', 'Bearer', 7200);
            }
        };

        self::assertTrue($authCodeHandler->supports(GrantType::AUTHORIZATION_CODE));
        self::assertFalse($authCodeHandler->supports(GrantType::CLIENT_CREDENTIALS));

        self::assertTrue($clientCredentialsHandler->supports(GrantType::CLIENT_CREDENTIALS));
        self::assertFalse($clientCredentialsHandler->supports(GrantType::AUTHORIZATION_CODE));

        $client = (new ClientBuilder())->build();
        $authCodeResult = $authCodeHandler->handle([], $client);
        self::assertSame('auth_code_token', $authCodeResult->accessToken);

        $clientCredResult = $clientCredentialsHandler->handle([], $client);
        self::assertSame('client_credentials_token', $clientCredResult->accessToken);
    }
}
