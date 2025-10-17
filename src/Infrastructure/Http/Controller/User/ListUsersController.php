<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User;

use App\Application\User\ListUser\ListUserQuery;
use App\Application\User\ListUser\ListUserQueryHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * User listing endpoints.
 */
final class ListUsersController extends AbstractController
{
    public function __construct(
        private readonly ListUserQueryHandler $listUserQueryHandler,
    ) {
    }

    /**
     * List users with pagination and sorting.
     */
    #[Route('/api/users', name: 'api_users_list', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        ListUsersQueryDTO $dto,
    ): JsonResponse {
        $query = new ListUserQuery(
            page: $dto->page,
            itemsPerPage: $dto->itemsPerPage,
            orderBy: $dto->orderBy,
            sortField: $dto->sortField
        );

        $result = ($this->listUserQueryHandler)($query);

        return $this->json([
            'users' => array_map(fn($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => $user->roles,
                'is_2fa_enabled' => $user->is2faEnabled,
                'created_at' => $user->createdAt->format(\DateTimeInterface::ATOM),
                'updated_at' => $user->updatedAt?->format(\DateTimeInterface::ATOM),
            ], $result['users']),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'items_per_page' => $result['itemsPerPage'],
                'total_pages' => $result['totalPages'],
            ],
        ], Response::HTTP_OK);
    }
}
