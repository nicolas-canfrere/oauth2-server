<?php

declare(strict_types=1);

namespace App\Service;

use Random\RandomException;

/**
 * Generates cryptographically secure opaque tokens for OAuth2 refresh tokens.
 *
 * This service creates random, unpredictable tokens using PHP's random_bytes()
 * function and encodes them in URL-safe base64url format. These tokens are
 * suitable for use as refresh tokens and authorization codes.
 *
 * Security characteristics:
 * - 32 bytes (256 bits) of cryptographic randomness
 * - Base64url encoding (URL-safe, no padding)
 * - Approximately 43 characters output length
 * - Must be hashed (SHA-256) before database storage
 */
final readonly class OpaqueTokenGenerator implements OpaqueTokenGeneratorInterface
{
    /**
     * Number of random bytes to generate for each token.
     * 32 bytes = 256 bits of entropy, recommended for cryptographic security.
     */
    private const int TOKEN_BYTES_LENGTH = 32;

    /**
     * Generates a cryptographically secure opaque token.
     *
     * The token is suitable for use as a refresh token or authorization code.
     * It must be hashed using TokenHasher before storing in the database.
     *
     * @return string A URL-safe base64-encoded random token (43 characters)
     *
     * @throws \RuntimeException If random_bytes() fails to generate secure random data
     */
    public function generate(): string
    {
        try {
            $randomBytes = random_bytes(self::TOKEN_BYTES_LENGTH);
        } catch (RandomException $exception) {
            throw new \RuntimeException(
                'Failed to generate secure random token',
                0,
                $exception
            );
        }

        return $this->base64UrlEncode($randomBytes);
    }

    /**
     * Encodes binary data to base64url format (RFC 4648 Section 5).
     *
     * Base64url encoding is URL-safe and does not contain characters that
     * require URL encoding ('+', '/', '='). This is essential for tokens
     * that may be transmitted in URL parameters.
     *
     * @param string $data Binary data to encode
     *
     * @return string Base64url-encoded string (no padding)
     */
    private function base64UrlEncode(string $data): string
    {
        $base64 = base64_encode($data);

        // Convert base64 to base64url: replace special characters and remove padding
        return rtrim(strtr($base64, '+/', '-_'), '=');
    }
}
