<?php

declare(strict_types=1);

namespace App\Model;

final readonly class OAuthClient
{
    /**
     * @param list<string> $grantTypes
     * @param list<string> $scopes
     */
    public function __construct(
        public string $id,
        public string $clientId,
        public string $clientSecretHash,
        public string $name,
        public string $redirectUri,
        public array $grantTypes,
        public array $scopes,
        public bool $isConfidential,
        public bool $pkceRequired,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
