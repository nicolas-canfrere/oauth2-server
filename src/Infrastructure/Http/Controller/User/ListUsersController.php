<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User;

use App\Application\User\ListUser\ListUserQuery;
use App\Application\User\ListUser\ListUserQueryHandler;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
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
    #[OA\Get(
        path: '/api/users',
        summary: 'List users with pagination and sorting',
        tags: ['Users']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns paginated list of users',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'users',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                            new OA\Property(property: 'is_2fa_enabled', type: 'boolean', example: false),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-10-17T10:30:00+00:00'),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-10-17T12:45:00+00:00', nullable: true),
                        ],
                        type: 'object'
                    )
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: '#/components/schemas/PaginationMeta',
                    type: 'object'
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid query parameters',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
            ],
            type: 'object'
        )
    )]
    #[Security(name: 'Bearer')]
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
