<?php

declare(strict_types=1);

namespace App\OAuth2\GrantHandler;

use App\Domain\OAuthClient\Model\OAuthClient;
use App\OAuth2\DTO\TokenResponseDTO;
use App\OAuth2\Exception\InvalidGrantException;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\OAuth2Exception;
use App\OAuth2\GrantType;

/**
 * Interface for OAuth2 Grant Type Handlers.
 *
 * Implements the Strategy pattern to handle different OAuth2 grant types
 * (authorization_code, client_credentials, refresh_token, etc.).
 *
 * Each grant handler is responsible for:
 * - Validating the grant-specific parameters
 * - Authenticating the client and/or resource owner
 * - Generating and returning access tokens (and optionally refresh tokens)
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4 (Grant Types)
 */
interface GrantHandlerInterface
{
    /**
     * Determines if this handler supports the given grant type.
     *
     * This method is called by the grant handler dispatcher to select
     * the appropriate handler for a token request.
     *
     * Example grant types:
     * - "authorization_code" (RFC 6749 Section 4.1)
     * - "client_credentials" (RFC 6749 Section 4.4)
     * - "refresh_token" (RFC 6749 Section 6)
     * - "password" (RFC 6749 Section 4.3, not recommended)
     *
     * @param GrantType $grantType The grant_type parameter from the token request
     *
     * @return bool True if this handler can process the grant type, false otherwise
     */
    public function supports(GrantType $grantType): bool;

    /**
     * Processes the grant request and returns a token response.
     *
     * This method MUST:
     * 1. Validate all required parameters for the grant type
     * 2. Authenticate the client (via client_id/client_secret or other methods)
     * 3. Perform grant-specific validation (e.g., verify authorization code, PKCE)
     * 4. Generate access token (and optionally refresh token)
     * 5. Return a TokenResponseDTO with the generated tokens
     *
     * Parameter array typically includes (varies by grant type):
     * - grant_type: The authorization grant type (REQUIRED)
     * - client_id: Client identifier (REQUIRED for public clients)
     * - client_secret: Client secret (REQUIRED for confidential clients)
     * - code: Authorization code (for authorization_code grant)
     * - redirect_uri: Redirection URI (for authorization_code grant)
     * - code_verifier: PKCE code verifier (for authorization_code grant with PKCE)
     * - refresh_token: Refresh token (for refresh_token grant)
     * - scope: Requested scope (OPTIONAL, space-separated)
     *
     * @param array<string, mixed> $parameters The token request parameters
     *
     * @return TokenResponseDTO the token response containing access_token, token_type, expires_in, etc
     *
     * @throws InvalidRequestException If required parameters are missing or malformed
     * @throws InvalidGrantException If the grant is invalid, expired, or revoked
     * @throws OAuth2Exception For other OAuth2-specific errors (invalid_client, unauthorized_client, etc.)
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4 (Authorization Grant)
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.1 (Successful Response)
     */
    public function handle(array $parameters, OAuthClient $client): TokenResponseDTO;
}
