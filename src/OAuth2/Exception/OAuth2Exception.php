<?php

declare(strict_types=1);

namespace App\OAuth2\Exception;

/**
 * Base exception class for OAuth2 errors conforming to RFC 6749.
 *
 * This exception represents OAuth2 protocol errors and provides structured
 * error information for JSON responses as specified in RFC 6749 Section 5.2.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
class OAuth2Exception extends \RuntimeException
{
    /**
     * @param string      $error            OAuth2 error code (e.g., "invalid_request", "invalid_grant")
     * @param string      $errorDescription Human-readable error description
     * @param string|null $errorUri         Optional URI to error documentation
     * @param int         $httpStatus       HTTP status code (e.g., 400, 401, 403, 500)
     * @param int         $code             Internal exception code (default: 0)
     * @param \Throwable|null $previous     Previous exception for chaining
     */
    public function __construct(
        private readonly string $error,
        private readonly string $errorDescription,
        private readonly ?string $errorUri = null,
        private readonly int $httpStatus = 400,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($errorDescription, $code, $previous);
    }

    /**
     * Get the OAuth2 error code.
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Get the human-readable error description.
     */
    public function getErrorDescription(): string
    {
        return $this->errorDescription;
    }

    /**
     * Get the optional URI to error documentation.
     */
    public function getErrorUri(): ?string
    {
        return $this->errorUri;
    }

    /**
     * Get the HTTP status code for this error.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Convert exception to array format conforming to RFC 6749.
     *
     * Returns an array suitable for JSON encoding with the following structure:
     * - error: OAuth2 error code
     * - error_description: Human-readable description
     * - error_uri: (optional) URI to documentation, only included if set
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $response = [
            'error' => $this->error,
            'error_description' => $this->errorDescription,
        ];

        if (null !== $this->errorUri) {
            $response['error_uri'] = $this->errorUri;
        }

        return $response;
    }
}
