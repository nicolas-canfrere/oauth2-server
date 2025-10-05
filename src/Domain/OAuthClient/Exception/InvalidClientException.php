<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Exception;

use App\OAuth2\Exception\OAuth2Exception;

/**
 * Exception thrown when client authentication failed
 * (e.g., unknown client, no client authentication included, or unsupported authentication method).
 *
 * RFC 6749 Error Code: invalid_client
 * HTTP Status: 401 Unauthorized
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class InvalidClientException extends OAuth2Exception
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
            error: 'invalid_client',
            errorDescription: $errorDescription ?? 'Client authentication failed (e.g., unknown client, no client authentication included, or unsupported authentication method).',
            errorUri: $errorUri,
            httpStatus: 401,
            code: $code,
            previous: $previous,
        );
    }
}
