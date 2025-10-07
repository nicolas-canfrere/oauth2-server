<?php

declare(strict_types=1);

namespace App\Application\OAuthClient\CreateOAuthClient;

final readonly class CreateOAuthClientCommand
{
    /**
     * @param string[] $grantTypes
     * @param string[] $scopes
     */
    public function __construct(
        public string $name,
        public string $redirectUri,
        public array $grantTypes,
        public array $scopes,
        public bool $isConfidential,
        public bool $pkceRequired,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
    ) {
    }
}
