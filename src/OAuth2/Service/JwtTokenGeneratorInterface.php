<?php

declare(strict_types=1);

namespace App\OAuth2\Service;

use App\OAuth2\DTO\JwtPayloadDTO;

/**
 * Interface for generating OAuth2 JWT access tokens.
 *
 * Generates signed JWT tokens using web-token/jwt-framework
 * with support for RSA and ECDSA signature algorithms.
 */
interface JwtTokenGeneratorInterface
{
    /**
     * Generate a signed JWT access token.
     *
     * The token is signed using the active cryptographic key from the database
     * and serialized in compact format (header.payload.signature).
     *
     * Supported algorithms: RS256, RS384, RS512, ES256, ES384, ES512
     *
     * @param JwtPayloadDTO $payload The JWT payload containing claims
     *
     * @return string The signed JWT token in compact serialization format
     *
     * @throws \RuntimeException If no active key is found or token generation fails
     */
    public function generate(JwtPayloadDTO $payload): string;
}
