<?php

declare(strict_types=1);

namespace App\Application\User\ListUser;

use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepositoryInterface;

final readonly class ListUserQueryHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Handle the list user query with pagination.
     *
     * @return array{users: User[], total: int, page: int, itemsPerPage: int, totalPages: int}
     */
    public function __invoke(ListUserQuery $query): array
    {
        return $this->userRepository->paginate(
            page: $query->page,
            itemsPerPage: $query->itemsPerPage,
            orderBy: $query->orderBy,
            sortField: $query->sortField
        );
    }
}
