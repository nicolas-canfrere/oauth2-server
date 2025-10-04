<?php

declare(strict_types=1);

namespace App\Tests\OAuth2\Exception;

use App\OAuth2\Exception\OAuth2Exception;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OAuth2Exception class.
 *
 * Verifies RFC 6749 compliance for OAuth2 error handling.
 */
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
}
