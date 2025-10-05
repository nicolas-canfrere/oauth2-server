<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\OAuthClient;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security user representation for OAuth2 clients.
 *
 * Wraps an OAuthClient model to make it compatible with Symfony Security.
 * Used for OAuth2 client authentication on token endpoint.
 */
final readonly class OAuth2ClientUser implements UserInterface
{
    public function __construct(
        private OAuthClient $client,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(): array
    {
        // OAuth2 clients have a special role
        return ['ROLE_OAUTH2_CLIENT'];
    }

    /**
     * {@inheritDoc}
     */
    public function eraseCredentials(): void
    {
        // Nothing to erase - client secret is not stored here
    }

    /**
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $clientId = $this->client->clientId;
        assert('' !== $clientId, 'Client ID cannot be empty');

        return $clientId;
    }

    /**
     * Get the wrapped OAuth2 client.
     */
    public function getClient(): OAuthClient
    {
        return $this->client;
    }
}
