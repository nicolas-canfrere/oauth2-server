<?php

declare(strict_types=1);

namespace App\Application\AccessToken\Exception;

/**
 * Exception thrown when the resource owner or authorization server denied the request.
 *
 * RFC 6749 Error Code: access_denied
 * HTTP Status: 403 Forbidden
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2.1
 */
final class AccessDeniedException extends OAuth2Exception
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
            error: 'access_denied',
            errorDescription: $errorDescription ?? 'The resource owner or authorization server denied the request.',
            errorUri: $errorUri,
            httpStatus: 403,
            code: $code,
            previous: $previous,
        );
    }
}
