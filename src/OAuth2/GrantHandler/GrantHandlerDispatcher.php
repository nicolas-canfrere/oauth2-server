<?php

declare(strict_types=1);

namespace App\OAuth2\GrantHandler;

use App\Domain\OAuthClient\Model\OAuthClient;
use App\OAuth2\DTO\TokenResponseDTO;
use App\OAuth2\Exception\InvalidRequestException;
use App\OAuth2\Exception\UnsupportedGrantTypeException;
use App\OAuth2\GrantType;

final readonly class GrantHandlerDispatcher
{
    /**
     * @param iterable<GrantHandlerInterface> $handlers
     */
    public function __construct(
        private iterable $handlers,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function dispatch(array $parameters, OAuthClient $client): TokenResponseDTO
    {
        $grantTypeString = $parameters['grant_type'] ?? throw new InvalidRequestException('Missing grant_type');

        if (!is_string($grantTypeString)) {
            throw new InvalidRequestException('The "grant_type" parameter must be a string.');
        }

        $grantType = GrantType::tryFrom($grantTypeString);

        if (null === $grantType) {
            throw new UnsupportedGrantTypeException(
                sprintf('Grant type "%s" is not supported', $grantTypeString)
            );
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($grantType)) {
                return $handler->handle($parameters, $client);
            }
        }

        throw new UnsupportedGrantTypeException(
            sprintf('Grant type "%s" is not supported', $grantType->value)
        );
    }
}
