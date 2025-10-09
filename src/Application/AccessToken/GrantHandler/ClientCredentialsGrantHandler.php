<?php

declare(strict_types=1);

namespace App\Application\AccessToken\GrantHandler;

use App\Application\AccessToken\DTO\JwtPayloadDTO;
use App\Application\AccessToken\DTO\TokenResponseDTO;
use App\Application\AccessToken\Enum\GrantType;
use App\Application\AccessToken\Exception\InvalidRequestException;
use App\Application\AccessToken\Service\JwtTokenGeneratorInterface;
use App\Domain\AccessToken\Event\AccessTokenIssuedEvent;
use App\Domain\OAuthClient\Exception\UnauthorizedClientException;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\Shared\Factory\IdentityFactoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ClientCredentialsGrantHandler implements GrantHandlerInterface
{
    public function __construct(
        private JwtTokenGeneratorInterface $jwtTokenGenerator,
        private IdentityFactoryInterface $identityFactory,
        private EventDispatcherInterface $eventDispatcher,
        private int $accessTokenTtl,
    ) {
    }

    public function supports(GrantType $grantType): bool
    {
        return GrantType::CLIENT_CREDENTIALS === $grantType;
    }

    public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
    {
        if (!$client->isConfidential) {
            throw new UnauthorizedClientException(
                'The client is not authorized to use the client_credentials grant type.'
            );
        }

        $scopes = $this->getScopesFromParameters($parameters);
        $scopeString = implode(' ', $scopes);

        // For client credentials, the subject of the token is the client itself.
        $jti = $this->identityFactory->generate();
        $payload = new JwtPayloadDTO(
            $client->clientId,
            $client->clientId,
            $scopeString,
            $this->accessTokenTtl,
            $client->clientId, // clientId parameter
            $jti // Pass JTI to ensure it matches the one in the event
        );

        $accessToken = $this->jwtTokenGenerator->generate($payload);

        // Dispatch domain event for audit logging
        $this->eventDispatcher->dispatch(new AccessTokenIssuedEvent(
            $client->clientId, // For client_credentials, userId is the clientId
            $client->clientId,
            GrantType::CLIENT_CREDENTIALS->value,
            $scopes,
            $jti
        ));

        return new TokenResponseDTO(
            $accessToken,
            'Bearer',
            $payload->expiresIn,
            null, // No refresh token for client_credentials
            $scopeString
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<string>
     */
    private function getScopesFromParameters(array $parameters): array
    {
        $scopeParameter = $parameters['scope'] ?? '';

        if (!is_string($scopeParameter)) {
            throw new InvalidRequestException('The "scope" parameter must be a string.');
        }

        if ('' === $scopeParameter) {
            return [];
        }

        return explode(' ', $scopeParameter);
    }
}
