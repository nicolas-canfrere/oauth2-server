<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\OAuthClient;

use App\Application\OAuthClient\ListOAuthClient\ListOAuthClientQuery;
use App\Application\OAuthClient\ListOAuthClient\ListOAuthClientQueryHandler;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuthClient listing endpoints.
 */
final class ListOAuthClientsController extends AbstractController
{
    public function __construct(
        private readonly ListOAuthClientQueryHandler $listOAuthClientQueryHandler,
    ) {
    }

    /**
     * List OAuth clients with pagination and sorting.
     */
    #[OA\Get(
        path: '/api/clients',
        summary: 'List OAuth clients with pagination and sorting',
        tags: ['OAuth Clients']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns paginated list of OAuth clients',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'clients',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'client_id', type: 'string', example: 'my-app-client'),
                            new OA\Property(property: 'name', type: 'string', example: 'My Application'),
                            new OA\Property(property: 'redirect_uri', type: 'string', format: 'uri', example: 'https://example.com/callback'),
                            new OA\Property(property: 'grant_types', type: 'array', items: new OA\Items(type: 'string'), example: ['authorization_code', 'refresh_token']),
                            new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), example: ['read', 'write']),
                            new OA\Property(property: 'is_confidential', type: 'boolean', example: true),
                            new OA\Property(property: 'pkce_required', type: 'boolean', example: false),
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
    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        ListOAuthClientsQueryDTO $dto,
    ): JsonResponse {
        $query = new ListOAuthClientQuery(
            page: $dto->page,
            itemsPerPage: $dto->itemsPerPage,
            orderBy: $dto->orderBy,
            sortField: $dto->sortField
        );

        $result = ($this->listOAuthClientQueryHandler)($query);

        return $this->json([
            'clients' => array_map(fn($client) => [
                'id' => $client->id,
                'client_id' => $client->clientId,
                'name' => $client->name,
                'redirect_uri' => $client->redirectUri,
                'grant_types' => $client->grantTypes,
                'scopes' => $client->scopes,
                'is_confidential' => $client->isConfidential,
                'pkce_required' => $client->pkceRequired,
                'created_at' => $client->createdAt->format(\DateTimeInterface::ATOM),
                'updated_at' => $client->updatedAt?->format(\DateTimeInterface::ATOM),
            ], $result['clients']),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'items_per_page' => $result['itemsPerPage'],
                'total_pages' => $result['totalPages'],
            ],
        ], Response::HTTP_OK);
    }
}
