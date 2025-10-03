<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthScope;

/**
 * Interface for OAuth2 scope repository operations.
 *
 * Provides methods for managing OAuth2 scopes using Doctrine DBAL.
 */
interface ScopeRepositoryInterface
{
    /**
     * Find all available scopes.
     *
     * @return list<OAuthScope> Array of all scope objects
     */
    public function findAll(): array;

    /**
     * Find scopes by their scope identifiers.
     *
     * @param list<string> $scopes Array of scope identifiers
     *
     * @return list<OAuthScope> Array of matching scope objects
     */
    public function findByScopes(array $scopes): array;

    /**
     * Get all default scopes.
     *
     * @return list<OAuthScope> Array of default scope objects
     */
    public function getDefaults(): array;

    /**
     * Save an OAuth2 scope (create or update).
     *
     * @param OAuthScope $scope The scope to save
     *
     * @throws \RuntimeException If save operation fails
     */
    public function save(OAuthScope $scope): void;

    /**
     * Find a scope by its internal ID.
     *
     * @param string $id The ID of the scope
     *
     * @return OAuthScope|null Scope object or null if not found
     */
    public function find(string $id): ?OAuthScope;
}
