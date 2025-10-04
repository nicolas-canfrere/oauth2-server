<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\GrantHandler;

use App\OAuth2\DTO\TokenResponseDTO;
use App\OAuth2\Exception\InvalidGrantException;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\GrantHandler\GrantHandlerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test suite to verify GrantHandlerInterface contract.
 *
 * This test uses a concrete implementation to verify the interface contract.
 */
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
        self::assertSame('string', $paramType->getName());

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
        self::assertSame(1, $method->getNumberOfParameters());

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
            public function supports(string $grantType): bool
            {
                return 'test_grant' === $grantType;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                return new TokenResponseDTO(
                    accessToken: 'test_token',
                    tokenType: 'Bearer',
                    expiresIn: 3600,
                );
            }
        };

        self::assertTrue($handler->supports('test_grant'));
        self::assertFalse($handler->supports('other_grant'));
    }

    public function testConcreteImplementationHandle(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(string $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                return new TokenResponseDTO(
                    accessToken: 'generated_access_token',
                    tokenType: 'Bearer',
                    expiresIn: 3600,
                    refreshToken: 'generated_refresh_token',
                    scope: 'user:read',
                );
            }
        };

        $result = $handler->handle([
            'grant_type' => 'test_grant',
            'client_id' => 'test_client',
        ]);

        self::assertSame('generated_access_token', $result->accessToken);
        self::assertSame('Bearer', $result->tokenType);
        self::assertSame(3600, $result->expiresIn);
        self::assertSame('generated_refresh_token', $result->refreshToken);
        self::assertSame('user:read', $result->scope);
    }

    public function testConcreteImplementationCanThrowInvalidRequestException(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(string $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                if (!isset($parameters['client_id'])) {
                    throw new InvalidRequestException('Missing required parameter: client_id');
                }

                return new TokenResponseDTO(
                    accessToken: 'test_token',
                    tokenType: 'Bearer',
                    expiresIn: 3600,
                );
            }
        };

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing required parameter: client_id');

        $handler->handle([]);
    }

    public function testConcreteImplementationCanThrowInvalidGrantException(): void
    {
        $handler = new class implements GrantHandlerInterface {
            public function supports(string $grantType): bool
            {
                return true;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                if (($parameters['code'] ?? null) === 'invalid_code') {
                    throw new InvalidGrantException('The authorization code is invalid or expired');
                }

                return new TokenResponseDTO(
                    accessToken: 'test_token',
                    tokenType: 'Bearer',
                    expiresIn: 3600,
                );
            }
        };

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('The authorization code is invalid or expired');

        $handler->handle(['code' => 'invalid_code']);
    }

    public function testMultipleImplementationsWithDifferentGrantTypes(): void
    {
        $authCodeHandler = new class implements GrantHandlerInterface {
            public function supports(string $grantType): bool
            {
                return 'authorization_code' === $grantType;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                return new TokenResponseDTO(
                    accessToken: 'auth_code_token',
                    tokenType: 'Bearer',
                    expiresIn: 3600,
                );
            }
        };

        $clientCredentialsHandler = new class implements GrantHandlerInterface {
            public function supports(string $grantType): bool
            {
                return 'client_credentials' === $grantType;
            }

            public function handle(array $parameters): TokenResponseDTO
            {
                return new TokenResponseDTO(
                    accessToken: 'client_credentials_token',
                    tokenType: 'Bearer',
                    expiresIn: 7200,
                );
            }
        };

        self::assertTrue($authCodeHandler->supports('authorization_code'));
        self::assertFalse($authCodeHandler->supports('client_credentials'));

        self::assertTrue($clientCredentialsHandler->supports('client_credentials'));
        self::assertFalse($clientCredentialsHandler->supports('authorization_code'));

        $authCodeResult = $authCodeHandler->handle([]);
        self::assertSame('auth_code_token', $authCodeResult->accessToken);

        $clientCredResult = $clientCredentialsHandler->handle([]);
        self::assertSame('client_credentials_token', $clientCredResult->accessToken);
    }
}
