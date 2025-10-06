<?php

declare(strict_types=1);

namespace App\Application\AccessToken\Exception;

/**
 * Exception thrown when the request is missing a required parameter,
 * includes an invalid parameter value, includes a parameter more than once,
 * or is otherwise malformed.
 *
 * RFC 6749 Error Code: invalid_request
 * HTTP Status: 400 Bad Request
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class InvalidRequestException extends OAuth2Exception
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
            error: 'invalid_request',
            errorDescription: $errorDescription ?? 'The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.',
            errorUri: $errorUri,
            httpStatus: 400,
            code: $code,
            previous: $previous,
        );
    }
}
