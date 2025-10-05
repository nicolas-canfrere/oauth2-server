<?php

declare(strict_types=1);

namespace App\OAuth2\GrantHandler;

use App\Domain\OAuthClient\Model\OAuthClient;
use App\OAuth2\DTO\JwtPayloadDTO;
use App\OAuth2\DTO\TokenResponseDTO;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\UnauthorizedClientException;
use App\OAuth2\GrantType;
use App\OAuth2\Service\JwtTokenGeneratorInterface;

final readonly class ClientCredentialsGrantHandler implements GrantHandlerInterface
{
    public function __construct(
        private JwtTokenGeneratorInterface $jwtTokenGenerator,
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
        $payload = new JwtPayloadDTO(
            $client->clientId,
            $client->clientId,
            $scopeString,
            $this->accessTokenTtl
        );

        $accessToken = $this->jwtTokenGenerator->generate($payload);

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
