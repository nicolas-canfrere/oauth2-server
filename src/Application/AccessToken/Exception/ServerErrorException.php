<?php

declare(strict_types=1);

namespace App\Application\AccessToken\Exception;

/**
 * Exception thrown when the authorization server encountered an unexpected condition
 * that prevented it from fulfilling the request.
 * (This error code is needed because a 500 Internal Server Error HTTP status code
 * cannot be returned to the client via an HTTP redirect.).
 *
 * RFC 6749 Error Code: server_error
 * HTTP Status: 500 Internal Server Error
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2.1
 */
final class ServerErrorException extends OAuth2Exception
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
            error: 'server_error',
            errorDescription: $errorDescription ?? 'The authorization server encountered an unexpected condition that prevented it from fulfilling the request.',
            errorUri: $errorUri,
            httpStatus: 500,
            code: $code,
            previous: $previous,
        );
    }
}
