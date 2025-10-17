<?php

declare(strict_types=1);

namespace App\Application\OAuthClient\ListOAuthClient;

use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\OAuthClient\Repository\ClientRepositoryInterface;

final readonly class ListOAuthClientQueryHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
    ) {
    }

    /**
     * Handle the list OAuth2 client query with pagination.
     *
     * @return array{clients: OAuthClient[], total: int, page: int, itemsPerPage: int, totalPages: int}
     */
    public function __invoke(ListOAuthClientQuery $query): array
    {
        return $this->clientRepository->paginate(
            page: $query->page,
            itemsPerPage: $query->itemsPerPage,
            orderBy: $query->orderBy,
            sortField: $query->sortField
        );
    }
}
