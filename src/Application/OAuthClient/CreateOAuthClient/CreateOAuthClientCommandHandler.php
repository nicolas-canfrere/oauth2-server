<?php

declare(strict_types=1);

namespace App\Application\OAuthClient\CreateOAuthClient;

use App\Domain\OAuthClient\Event\OAuthClientCreatedEvent;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\OAuthClient\Repository\ClientRepositoryInterface;
use App\Domain\OAuthClient\Service\ClientSecretGeneratorInterface;
use App\Domain\Shared\Factory\IdentityFactoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CreateOAuthClientCommandHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ClientSecretGeneratorInterface $clientSecretGenerator,
        private IdentityFactoryInterface $identityFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array{client_id: string, client_secret: ?string}
     */
    public function __invoke(CreateOAuthClientCommand $command): array
    {
        $now = new \DateTimeImmutable();
        $clientId = $command->clientId ?? $this->identityFactory->generate();

        $grantTypes = array_values(array_filter($command->grantTypes, 'is_string'));
        $scopes = array_values(array_filter($command->scopes, 'is_string'));

        $plainSecret = null;
        $clientSecretHash = null;

        if ($command->isConfidential) {
            $plainSecret = $command->clientSecret ?? $this->clientSecretGenerator->generate();
            $this->clientSecretGenerator->validate($plainSecret);

            $clientSecretHash = password_hash($plainSecret, PASSWORD_BCRYPT);
        }

        $client = new OAuthClient(
            id: $this->identityFactory->generate(),
            clientId: $clientId,
            clientSecretHash: $clientSecretHash,
            name: $command->name,
            redirectUri: $command->redirectUri,
            grantTypes: $grantTypes,
            scopes: $scopes,
            isConfidential: $command->isConfidential,
            pkceRequired: $command->pkceRequired,
            createdAt: $now,
        );

        $this->clientRepository->create($client);

        // Dispatch domain event for client creation
        $this->eventDispatcher->dispatch(
            new OAuthClientCreatedEvent(
                clientId: $clientId,
                clientName: $command->name,
                redirectUri: $command->redirectUri,
                grantTypes: $grantTypes,
                scopes: $scopes,
                isConfidential: $command->isConfidential,
                pkceRequired: $command->pkceRequired,
            )
        );

        return [
            'client_id' => $clientId,
            'client_secret' => $plainSecret,
        ];
    }
}
