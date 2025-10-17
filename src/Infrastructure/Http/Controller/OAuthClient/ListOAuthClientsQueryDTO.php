<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\OAuthClient;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for OAuth client listing query parameters.
 */
final readonly class ListOAuthClientsQueryDTO
{
    /**
     * @param int    $page         Current page number (1-indexed, minimum: 1)
     * @param int    $itemsPerPage Number of items per page (minimum: 1, maximum: 100)
     * @param string $orderBy      Sort direction: 'asc' or 'desc'
     * @param string $sortField    Field to sort by: 'name', 'client_id', 'created_at', or 'id'
     */
    public function __construct(
        #[Assert\Positive(message: 'Page must be greater than 0.')]
        public int $page = 1,
        #[Assert\Positive(message: 'Items per page must be greater than 0.')]
        #[Assert\LessThanOrEqual(value: 100, message: 'Items per page cannot exceed 100.')]
        public int $itemsPerPage = 10,
        #[Assert\Choice(choices: ['asc', 'desc'], message: 'Order direction must be "asc" or "desc".')]
        public string $orderBy = 'asc',
        #[Assert\Choice(choices: ['name', 'client_id', 'created_at', 'id'], message: 'Invalid sort field. Allowed fields: name, client_id, created_at, id.')]
        public string $sortField = 'name',
    ) {
    }
}
