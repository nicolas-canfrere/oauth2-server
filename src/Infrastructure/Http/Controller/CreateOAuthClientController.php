<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommand;
use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommandHandler;
use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientDTO;
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
