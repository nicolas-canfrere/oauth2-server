<?php

declare(strict_types=1);

namespace App\OAuth2\Exception;

/**
 * Exception thrown when the provided authorization grant (e.g., authorization code,
 * resource owner credentials) or refresh token is invalid, expired, revoked,
 * does not match the redirection URI used in the authorization request,
 * or was issued to another client.
 *
 * RFC 6749 Error Code: invalid_grant
 * HTTP Status: 400 Bad Request
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class InvalidGrantException extends OAuth2Exception
{
    /**
     * @param string|null $errorDescription Custom error description (default: RFC-compliant generic message)
     * @param string|null $errorUri Optional URI to error documentation
     * @param int $code Internal exception code (default: 0)
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        ?string $errorDescription = null,
        ?string $errorUri = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            error: 'invalid_grant',
            errorDescription: $errorDescription ?? 'The provided authorization grant or refresh token is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.',
            errorUri: $errorUri,
            httpStatus: 400,
            code: $code,
            previous: $previous,
        );
    }
}
