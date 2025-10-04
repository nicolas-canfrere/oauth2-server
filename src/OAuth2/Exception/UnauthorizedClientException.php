<?php

declare(strict_types=1);

namespace App\OAuth2\Exception;

/**
 * Exception thrown when the authenticated client is not authorized to use
 * the requested authorization grant type.
 *
 * RFC 6749 Error Code: unauthorized_client
 * HTTP Status: 403 Forbidden
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class UnauthorizedClientException extends OAuth2Exception
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
            error: 'unauthorized_client',
            errorDescription: $errorDescription ?? 'The authenticated client is not authorized to use this authorization grant type.',
            errorUri: $errorUri,
            httpStatus: 403,
            code: $code,
            previous: $previous,
        );
    }
}
