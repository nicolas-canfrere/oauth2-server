<?php

declare(strict_types=1);

namespace App\Application\AccessToken\Exception;

/**
 * Exception thrown when the authorization grant type is not supported
 * by the authorization server.
 *
 * RFC 6749 Error Code: unsupported_grant_type
 * HTTP Status: 400 Bad Request
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class UnsupportedGrantTypeException extends OAuth2Exception
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
            error: 'unsupported_grant_type',
            errorDescription: $errorDescription ?? 'The authorization grant type is not supported by the authorization server.',
            errorUri: $errorUri,
            httpStatus: 400,
            code: $code,
            previous: $previous,
        );
    }
}
