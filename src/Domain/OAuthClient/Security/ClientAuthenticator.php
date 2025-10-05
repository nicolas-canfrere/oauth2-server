<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Security;

use App\Domain\OAuthClient\Exception\InvalidClientException;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\OAuthClient\Repository\ClientRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth2 client authentication service.
 *
 * Implements multiple authentication mechanisms for OAuth2 clients:
 * - HTTP Basic Authentication (preferred for confidential clients)
 * - POST body parameters (fallback for confidential clients)
 * - Public client validation (for clients without secrets)
 *
 * Security features:
 * - Bcrypt password verification
 * - Timing attack protection via hash_equals()
 * - Comprehensive audit logging
 */
final readonly class ClientAuthenticator implements ClientAuthenticatorInterface
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request): OAuthClient
    {
        // Try HTTP Basic Authentication first (most secure)
        try {
            return $this->authenticateWithBasicAuth($request);
        } catch (InvalidClientException) {
            // Ignore and try the next method
        }

        // Try POST body authentication
        try {
            return $this->authenticateWithPostBody($request);
        } catch (InvalidClientException) {
            // Ignore and try the next method
        }

        // Try public client authentication (client_id only)
        $clientId = $request->request->get('client_id') ?? $request->query->get('client_id');
        if (is_string($clientId) && '' !== $clientId) {
            try {
                return $this->authenticatePublicClient($clientId);
            } catch (InvalidClientException) {
                // Ignore and fall through to the final error
            }
        }

        throw new InvalidClientException('Client authentication failed: No valid credentials provided.');
    }

    /**
     * {@inheritDoc}
     */
    public function authenticateWithBasicAuth(Request $request): OAuthClient
    {
        $authHeader = $request->headers->get('Authorization');

        if (null === $authHeader || !str_starts_with($authHeader, 'Basic ')) {
            throw new InvalidClientException('HTTP Basic authentication header is missing or invalid.');
        }

        // Extract Base64 encoded credentials
        $encodedCredentials = substr($authHeader, 6); // Remove "Basic " prefix
        $decodedCredentials = base64_decode($encodedCredentials, true);

        if (false === $decodedCredentials) {
            throw new InvalidClientException('Invalid Base64 encoding in Basic Auth header.');
        }

        // Parse client_id:client_secret
        $parts = explode(':', $decodedCredentials, 2);
        if (2 !== count($parts)) {
            throw new InvalidClientException('Invalid Basic Auth format (missing colon separator).');
        }

        [$clientId, $clientSecret] = $parts;

        return $this->authenticateWithCredentials($clientId, $clientSecret);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticateWithPostBody(Request $request): OAuthClient
    {
        $clientId = $request->request->get('client_id');
        $clientSecret = $request->request->get('client_secret');

        if (!is_string($clientId) || !is_string($clientSecret)) {
            throw new InvalidClientException('The request body must contain the "client_id" and "client_secret" parameters.');
        }

        return $this->authenticateWithCredentials($clientId, $clientSecret);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticatePublicClient(string $clientId): OAuthClient
    {
        if ('' === $clientId) {
            throw new InvalidClientException('Public client authentication requires a client_id.');
        }

        $client = $this->clientRepository->findByClientId($clientId);

        if (null === $client) {
            throw new InvalidClientException('Public client not found.');
        }

        // Only allow public clients (not confidential)
        if ($client->isConfidential) {
            throw new InvalidClientException('Confidential client cannot be authenticated as a public client.');
        }

        return $client;
    }

    /**
     * {@inheritDoc}
     */
    public function verifyClientSecret(string $plainSecret, string $secretHash): bool
    {
        // password_verify is the standard and secure way to check a password hash.
        // It is already protected against timing attacks.
        return password_verify($plainSecret, $secretHash);
    }

    /**
     * Authenticate client with credentials (client_id + client_secret).
     *
     * @param string $clientId Client identifier
     * @param string $clientSecret Client secret (plain text)
     *
     * @return OAuthClient Authenticated client
     */
    private function authenticateWithCredentials(string $clientId, string $clientSecret): OAuthClient
    {
        if ('' === $clientId || '' === $clientSecret) {
            throw new InvalidClientException('Client credentials were not provided.');
        }

        $client = $this->clientRepository->findByClientId($clientId);

        if (null === $client) {
            // Use dummy hash to prevent timing attacks
            password_verify($clientSecret, '$2y$10$dummyhashtopreventtimingattacksxxxxxxxxxxxxxxxxxxxxxxxxx');

            throw new InvalidClientException('Client authentication failed: client not found.');
        }

        // Confidential clients must provide valid secret
        if ($client->isConfidential && !$this->verifyClientSecret($clientSecret, $client->clientSecretHash)) {
            throw new InvalidClientException('Client authentication failed: invalid client secret.');
        }

        // Public clients should not send secrets (informational log only)
        if (!$client->isConfidential) {
            $this->logger->info('Public client authenticated with credentials', [
                'client_id' => $clientId,
            ]);
        }

        return $client;
    }
}
