<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthClient;

/**
 * Interface for OAuth2 client repository operations.
 *
 * Provides methods for managing OAuth2 clients using Doctrine DBAL.
 */
interface ClientRepositoryInterface
{
    /**
     * Find a client by its internal ID.
     *
     * @param string $id The ID of the client
     *
     * @return OAuthClient|null Client object or null if not found
     */
    public function find(string $id): ?OAuthClient;

    /**
     * Find a client by its OAuth2 client_id (public identifier).
     *
     * This method is essential for OAuth2 flows where clients authenticate
     * using their client_id, not the internal database UUID.
     *
     * @param string $clientId The OAuth2 client identifier
     *
     * @return OAuthClient|null Client object or null if not found
     */
    public function findByClientId(string $clientId): ?OAuthClient;

    /**
     * Create a new OAuth2 client.
     *
     * @param OAuthClient $client The client to create
     *
     * @throws \RuntimeException If client already exists or creation fails
     */
    public function create(OAuthClient $client): void;

    /**
     * Update an existing OAuth2 client.
     *
     * @param OAuthClient $client The client to update
     *
     * @throws \RuntimeException If update operation fails
     */
    public function update(OAuthClient $client): void;

    /**
     * Delete an OAuth2 client.
     *
     * @param string $id The ID of the client to delete
     *
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(string $id): bool;

    /**
     * Find all clients (with optional pagination).
     *
     * @param int $limit  Maximum number of results
     * @param int $offset Starting offset
     *
     * @return list<OAuthClient> Array of client objects
     */
    public function findAll(int $limit = 100, int $offset = 0): array;
}
