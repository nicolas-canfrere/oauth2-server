<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\OAuthClient;

use App\Application\OAuthClient\ListOAuthClient\ListOAuthClientQuery;
use App\Application\OAuthClient\ListOAuthClient\ListOAuthClientQueryHandler;
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
