<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\OAuthClient;

use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommand;
use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommandHandler;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth Client management endpoints.
 */
final class CreateOAuthClientController extends AbstractController
{
    public function __construct(
        private readonly CreateOAuthClientCommandHandler $createClientHandler,
    ) {
    }

    /**
     * Create a new OAuth client.
     */
    #[OA\Post(
        path: '/api/clients',
        summary: 'Create a new OAuth client',
        tags: ['OAuth Clients']
    )]
    #[OA\Response(
        response: 201,
        description: 'OAuth client created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'client_id', type: 'string', example: 'my-app-client'),
                new OA\Property(property: 'client_secret', type: 'string', example: 'secret_abc123xyz'),
                new OA\Property(property: 'name', type: 'string', example: 'My Application'),
                new OA\Property(
                    property: 'redirect_uris',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uri'),
                    example: ['https://example.com/callback']
                ),
                new OA\Property(
                    property: 'grant_types',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['authorization_code', 'refresh_token']
                ),
                new OA\Property(
                    property: 'scopes',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['read', 'write']
                ),
                new OA\Property(property: 'is_confidential', type: 'boolean', example: true),
                new OA\Property(property: 'pkce_required', type: 'boolean', example: false),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
            ],
            type: 'object'
        )
    )]
    #[Security(name: 'Bearer')]
    #[Route('/api/clients', name: 'api_oauth_clients_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        CreateOAuthClientDTO $dto,
    ): JsonResponse {
        $command = new CreateOAuthClientCommand(
            name: $dto->name,
            redirectUri: $dto->redirectUris[0],
            grantTypes: $dto->grantTypes,
            scopes: $dto->scopes,
            isConfidential: $dto->isConfidential,
            pkceRequired: $dto->pkceRequired,
            clientId: $dto->clientId,
            clientSecret: $dto->clientSecret,
        );

        $result = ($this->createClientHandler)($command);

        return $this->json([
            'client_id' => $result['client_id'],
            'client_secret' => $result['client_secret'],
            'name' => $dto->name,
            'redirect_uris' => $dto->redirectUris,
            'grant_types' => $dto->grantTypes,
            'scopes' => $dto->scopes,
            'is_confidential' => $dto->isConfidential,
            'pkce_required' => $dto->pkceRequired,
        ], Response::HTTP_CREATED);
    }
}
