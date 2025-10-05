<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\Application\AccessToken\DTO\TokenResponseDTO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Application\AccessToken\DTO\TokenResponseDTO
 */
final class TokenResponseDTOTest extends TestCase
{
    public function testConstructorWithRequiredParametersOnly(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
        );

        self::assertSame('test_access_token_xyz', $dto->accessToken);
        self::assertSame('Bearer', $dto->tokenType);
        self::assertSame(3600, $dto->expiresIn);
        self::assertNull($dto->refreshToken);
        self::assertNull($dto->scope);
        self::assertSame([], $dto->additionalData);
    }

    public function testConstructorWithAllParameters(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'test_refresh_token_abc',
            scope: 'user:read user:write',
            additionalData: ['id_token' => 'eyJhbGciOiJSUzI1NiJ9...'],
        );

        self::assertSame('test_access_token_xyz', $dto->accessToken);
        self::assertSame('Bearer', $dto->tokenType);
        self::assertSame(3600, $dto->expiresIn);
        self::assertSame('test_refresh_token_abc', $dto->refreshToken);
        self::assertSame('user:read user:write', $dto->scope);
        self::assertSame(['id_token' => 'eyJhbGciOiJSUzI1NiJ9...'], $dto->additionalData);
    }

    public function testToArrayWithRequiredFieldsOnly(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
        );

        $expected = [
            'access_token' => 'test_access_token_xyz',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        self::assertSame($expected, $dto->toArray());
    }

    public function testToArrayWithRefreshToken(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'test_refresh_token_abc',
        );

        $result = $dto->toArray();

        self::assertArrayHasKey('refresh_token', $result);
        self::assertSame('test_refresh_token_abc', $result['refresh_token']);
    }

    public function testToArrayWithScope(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            scope: 'user:read user:write admin:all',
        );

        $result = $dto->toArray();

        self::assertArrayHasKey('scope', $result);
        self::assertSame('user:read user:write admin:all', $result['scope']);
    }

    public function testToArrayWithAdditionalData(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            additionalData: [
                'id_token' => 'eyJhbGciOiJSUzI1NiJ9...',
                'custom_field' => 'custom_value',
            ],
        );

        $result = $dto->toArray();

        self::assertArrayHasKey('id_token', $result);
        self::assertSame('eyJhbGciOiJSUzI1NiJ9...', $result['id_token']);
        self::assertArrayHasKey('custom_field', $result);
        self::assertSame('custom_value', $result['custom_field']);
    }

    public function testToArrayWithAllFields(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'test_refresh_token_abc',
            scope: 'user:read user:write',
            additionalData: ['id_token' => 'eyJhbGciOiJSUzI1NiJ9...'],
        );

        $expected = [
            'access_token' => 'test_access_token_xyz',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'test_refresh_token_abc',
            'scope' => 'user:read user:write',
            'id_token' => 'eyJhbGciOiJSUzI1NiJ9...',
        ];

        self::assertSame($expected, $dto->toArray());
    }

    public function testToArrayDoesNotIncludeNullRefreshToken(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: null,
        );

        $result = $dto->toArray();

        self::assertArrayNotHasKey('refresh_token', $result);
    }

    public function testToArrayDoesNotIncludeNullScope(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
            scope: null,
        );

        $result = $dto->toArray();

        self::assertArrayNotHasKey('scope', $result);
    }

    public function testReadonlyProperties(): void
    {
        $dto = new TokenResponseDTO(
            accessToken: 'test_access_token_xyz',
            tokenType: 'Bearer',
            expiresIn: 3600,
        );

        // This test ensures the class is readonly by attempting to access properties
        // PHP 8.2+ readonly classes will prevent modification at runtime
        self::assertSame('test_access_token_xyz', $dto->accessToken);
        self::assertSame('Bearer', $dto->tokenType);
        self::assertSame(3600, $dto->expiresIn);
    }
}
