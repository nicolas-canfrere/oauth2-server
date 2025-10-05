<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Security;

use App\Domain\OAuthClient\Model\OAuthClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for OAuth2 client authentication.
 *
 * Provides methods to authenticate OAuth2 clients using various mechanisms:
 * - HTTP Basic Authentication (RFC 2617)
 * - POST body parameters (client_id + client_secret)
 * - Public clients (no secret required)
 */
interface ClientAuthenticatorInterface
{
    /**
     * Authenticate a client from an HTTP request.
     *
     * Tries authentication in the following order:
     * 1. HTTP Basic Authentication (Authorization header)
     * 2. POST body parameters (client_id + client_secret)
     * 3. Public client (client_id only, no secret)
     *
     * @param Request $request HTTP request containing client credentials
     *
     * @return OAuthClient Authenticated client
     *
     * @throws \App\Domain\OAuthClient\Exception\InvalidClientException If authentication fails
     */
    public function authenticate(Request $request): OAuthClient;

    /**
     * Authenticate a client using HTTP Basic Authentication.
     *
     * Expects "Authorization: Basic <base64(client_id:client_secret)>" header.
     *
     * @param Request $request HTTP request with Basic Auth header
     *
     * @return OAuthClient Authenticated client
     *
     * @throws \App\Domain\OAuthClient\Exception\InvalidClientException If authentication fails
     */
    public function authenticateWithBasicAuth(Request $request): OAuthClient;

    /**
     * Authenticate a client using POST body parameters.
     *
     * Expects "client_id" and "client_secret" in request body.
     *
     * @param Request $request HTTP request with POST body parameters
     *
     * @return OAuthClient Authenticated client
     *
     * @throws \App\Domain\OAuthClient\Exception\InvalidClientException If authentication fails
     */
    public function authenticateWithPostBody(Request $request): OAuthClient;

    /**
     * Authenticate a public client (no secret required).
     *
     * Public clients are authenticated by client_id only.
     * Used for mobile/SPA applications that cannot securely store secrets.
     *
     * @param string $clientId Client identifier
     *
     * @return OAuthClient Authenticated public client
     *
     * @throws \App\Domain\OAuthClient\Exception\InvalidClientException If client is not found or is not a public client
     */
    public function authenticatePublicClient(string $clientId): OAuthClient;

    /**
     * Verify client secret against stored hash.
     *
     * Uses constant-time comparison (hash_equals) to prevent timing attacks.
     *
     * @param string $plainSecret Plain text secret from request
     * @param string $secretHash Bcrypt hash stored in database
     *
     * @return bool True if secret matches, false otherwise
     */
    public function verifyClientSecret(string $plainSecret, string $secretHash): bool;
}
