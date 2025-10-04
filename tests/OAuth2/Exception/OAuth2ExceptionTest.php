<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\Exception;

use App\OAuth2\Exception\AccessDeniedException;
use App\OAuth2\Exception\InvalidClientException;
use App\OAuth2\Exception\InvalidGrantException;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\InvalidScopeException;
use App\OAuth2\Exception\OAuth2Exception;
use App\OAuth2\Exception\ServerErrorException;
use App\OAuth2\Exception\UnauthorizedClientException;
use App\OAuth2\Exception\UnsupportedGrantTypeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OAuth2Exception class and all specific RFC 6749 exception types.
 *
 * Verifies RFC 6749 compliance for OAuth2 error handling.
 */
#[CoversClass(OAuth2Exception::class)]
#[CoversClass(InvalidRequestException::class)]
#[CoversClass(InvalidClientException::class)]
#[CoversClass(InvalidGrantException::class)]
#[CoversClass(UnauthorizedClientException::class)]
#[CoversClass(UnsupportedGrantTypeException::class)]
#[CoversClass(InvalidScopeException::class)]
#[CoversClass(AccessDeniedException::class)]
#[CoversClass(ServerErrorException::class)]
final class OAuth2ExceptionTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $exception = new OAuth2Exception(
            error: 'invalid_request',
            errorDescription: 'The request is missing a required parameter',
            errorUri: 'https://example.com/docs/errors#invalid_request',
            httpStatus: 400
        );

        self::assertSame('invalid_request', $exception->getError());
        self::assertSame('The request is missing a required parameter', $exception->getErrorDescription());
        self::assertSame('https://example.com/docs/errors#invalid_request', $exception->getErrorUri());
        self::assertSame(400, $exception->getHttpStatus());
    }

    public function testConstructorWithoutErrorUri(): void
    {
        $exception = new OAuth2Exception(
            error: 'invalid_grant',
            errorDescription: 'The provided authorization grant is invalid',
            httpStatus: 400
        );

        self::assertSame('invalid_grant', $exception->getError());
        self::assertSame('The provided authorization grant is invalid', $exception->getErrorDescription());
        self::assertNull($exception->getErrorUri());
        self::assertSame(400, $exception->getHttpStatus());
    }

    public function testGetErrorReturnsCorrectValue(): void
    {
        $exception = new OAuth2Exception(
            error: 'unauthorized_client',
            errorDescription: 'The client is not authorized',
            httpStatus: 401
        );

        self::assertSame('unauthorized_client', $exception->getError());
    }

    public function testGetErrorDescriptionReturnsCorrectValue(): void
    {
        $description = 'Access token has expired';
        $exception = new OAuth2Exception(
            error: 'invalid_token',
            errorDescription: $description,
            httpStatus: 401
        );

        self::assertSame($description, $exception->getErrorDescription());
    }

    public function testGetErrorUriReturnsCorrectValue(): void
    {
        $uri = 'https://oauth.net/2/errors/';
        $exception = new OAuth2Exception(
            error: 'server_error',
            errorDescription: 'An unexpected error occurred',
            errorUri: $uri,
            httpStatus: 500
        );

        self::assertSame($uri, $exception->getErrorUri());
    }

    public function testGetHttpStatusReturnsCorrectValue(): void
    {
        $exception = new OAuth2Exception(
            error: 'access_denied',
            errorDescription: 'The resource owner denied the request',
            httpStatus: 403
        );

        self::assertSame(403, $exception->getHttpStatus());
    }

    public function testToArrayWithErrorUri(): void
    {
        $exception = new OAuth2Exception(
            error: 'invalid_scope',
            errorDescription: 'The requested scope is invalid',
            errorUri: 'https://example.com/docs/scopes',
            httpStatus: 400
        );

        $expected = [
            'error' => 'invalid_scope',
            'error_description' => 'The requested scope is invalid',
            'error_uri' => 'https://example.com/docs/scopes',
        ];

        self::assertSame($expected, $exception->toArray());
    }

    public function testToArrayWithoutErrorUri(): void
    {
        $exception = new OAuth2Exception(
            error: 'unsupported_grant_type',
            errorDescription: 'The grant type is not supported',
            httpStatus: 400
        );

        $expected = [
            'error' => 'unsupported_grant_type',
            'error_description' => 'The grant type is not supported',
        ];

        self::assertSame($expected, $exception->toArray());
        self::assertArrayNotHasKey('error_uri', $exception->toArray());
    }

    public function testGetMessageReturnsErrorDescription(): void
    {
        $description = 'The authorization server encountered an unexpected condition';
        $exception = new OAuth2Exception(
            error: 'server_error',
            errorDescription: $description,
            httpStatus: 500
        );

        self::assertSame($description, $exception->getMessage());
    }

    public function testExceptionCodeCanBeSet(): void
    {
        $exception = new OAuth2Exception(
            error: 'invalid_request',
            errorDescription: 'Invalid parameter',
            httpStatus: 400,
            code: 1001
        );

        self::assertSame(1001, $exception->getCode());
    }

    public function testPreviousExceptionCanBeChained(): void
    {
        $previous = new \Exception('Original error');
        $exception = new OAuth2Exception(
            error: 'server_error',
            errorDescription: 'Internal error occurred',
            httpStatus: 500,
            previous: $previous
        );

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testDifferentHttpStatusCodes(): void
    {
        $testCases = [
            ['error' => 'invalid_request', 'status' => 400],
            ['error' => 'invalid_client', 'status' => 401],
            ['error' => 'access_denied', 'status' => 403],
            ['error' => 'server_error', 'status' => 500],
        ];

        foreach ($testCases as $testCase) {
            $exception = new OAuth2Exception(
                error: $testCase['error'],
                errorDescription: 'Test error',
                httpStatus: $testCase['status']
            );

            self::assertSame($testCase['status'], $exception->getHttpStatus());
        }
    }

    /**
     * Tests that specific OAuth2 exceptions use correct error codes and HTTP status codes
     * as defined in RFC 6749.
     *
     * @param class-string<OAuth2Exception> $exceptionClass
     */
    #[DataProvider('provideSpecificExceptionData')]
    public function testSpecificExceptionsUseCorrectErrorCodesAndHttpStatus(
        string $exceptionClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
        string $expectedDefaultDescription,
    ): void {
        /** @var OAuth2Exception $exception */
        $exception = new $exceptionClass(); // @phpstan-ignore arguments.count

        self::assertSame($expectedErrorCode, $exception->getError());
        self::assertSame($expectedHttpStatus, $exception->getHttpStatus());
        self::assertSame($expectedDefaultDescription, $exception->getErrorDescription());
        self::assertNull($exception->getErrorUri());
    }

    /**
     * Tests that specific OAuth2 exceptions accept custom descriptions and error URIs.
     *
     * @param class-string<OAuth2Exception> $exceptionClass
     */
    #[DataProvider('provideSpecificExceptionData')]
    public function testSpecificExceptionsWithCustomDescription(
        string $exceptionClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
        string $expectedDefaultDescription,
    ): void {
        $customDescription = 'Custom error description for testing';
        $customErrorUri = 'https://example.com/custom-error';

        /** @var OAuth2Exception $exception */
        $exception = new $exceptionClass( // @phpstan-ignore argument.missing
            errorDescription: $customDescription,
            errorUri: $customErrorUri,
        );

        self::assertSame($expectedErrorCode, $exception->getError());
        self::assertSame($expectedHttpStatus, $exception->getHttpStatus());
        self::assertSame($customDescription, $exception->getErrorDescription());
        self::assertSame($customErrorUri, $exception->getErrorUri());
    }

    /**
     * Tests that specific OAuth2 exceptions produce correct JSON array format.
     *
     * @param class-string<OAuth2Exception> $exceptionClass
     */
    #[DataProvider('provideSpecificExceptionData')]
    public function testSpecificExceptionsToArrayFormat(
        string $exceptionClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
        string $expectedDefaultDescription,
    ): void {
        /** @var OAuth2Exception $exception */
        $exception = new $exceptionClass( // @phpstan-ignore argument.missing
            errorDescription: 'Custom error',
            errorUri: 'https://example.com/error',
        );

        $array = $exception->toArray();

        self::assertArrayHasKey('error', $array);
        self::assertArrayHasKey('error_description', $array);
        self::assertArrayHasKey('error_uri', $array);
        self::assertSame($expectedErrorCode, $array['error']);
        self::assertSame('Custom error', $array['error_description']);
        self::assertSame('https://example.com/error', $array['error_uri']);
    }

    /**
     * Tests that specific OAuth2 exceptions extend RuntimeException.
     *
     * @param class-string<OAuth2Exception> $exceptionClass
     */
    #[DataProvider('provideSpecificExceptionData')]
    public function testSpecificExceptionsExtendsRuntimeException(
        string $exceptionClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
        string $expectedDefaultDescription,
    ): void {
        /** @var OAuth2Exception $exception */
        $exception = new $exceptionClass(); // @phpstan-ignore arguments.count

        self::assertInstanceOf(\RuntimeException::class, $exception); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertInstanceOf(OAuth2Exception::class, $exception); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    /**
     * Tests that specific OAuth2 exceptions can be thrown.
     *
     * @param class-string<OAuth2Exception> $exceptionClass
     */
    #[DataProvider('provideSpecificExceptionData')]
    public function testSpecificExceptionsCanBeThrown(
        string $exceptionClass,
        string $expectedErrorCode,
        int $expectedHttpStatus,
        string $expectedDefaultDescription,
    ): void {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage('Test exception message');

        throw new $exceptionClass(errorDescription: 'Test exception message'); // @phpstan-ignore argument.missing
    }

    /**
     * Provides test data for all specific OAuth2 exceptions with their expected values
     * according to RFC 6749 specification.
     *
     * @return iterable<string, array{class-string<OAuth2Exception>, string, int, string}>
     */
    public static function provideSpecificExceptionData(): iterable
    {
        yield 'InvalidRequestException' => [
            InvalidRequestException::class,
            'invalid_request',
            400,
            'The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.',
        ];

        yield 'InvalidClientException' => [
            InvalidClientException::class,
            'invalid_client',
            401,
            'Client authentication failed (e.g., unknown client, no client authentication included, or unsupported authentication method).',
        ];

        yield 'InvalidGrantException' => [
            InvalidGrantException::class,
            'invalid_grant',
            400,
            'The provided authorization grant or refresh token is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.',
        ];

        yield 'UnauthorizedClientException' => [
            UnauthorizedClientException::class,
            'unauthorized_client',
            403,
            'The authenticated client is not authorized to use this authorization grant type.',
        ];

        yield 'UnsupportedGrantTypeException' => [
            UnsupportedGrantTypeException::class,
            'unsupported_grant_type',
            400,
            'The authorization grant type is not supported by the authorization server.',
        ];

        yield 'InvalidScopeException' => [
            InvalidScopeException::class,
            'invalid_scope',
            400,
            'The requested scope is invalid, unknown, malformed, or exceeds the scope granted by the resource owner.',
        ];

        yield 'AccessDeniedException' => [
            AccessDeniedException::class,
            'access_denied',
            403,
            'The resource owner or authorization server denied the request.',
        ];

        yield 'ServerErrorException' => [
            ServerErrorException::class,
            'server_error',
            500,
            'The authorization server encountered an unexpected condition that prevented it from fulfilling the request.',
        ];
    }
}
