<?php

declare(strict_types=1);

namespace App\Domain\Consent\Model;

final readonly class UserConsent
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $clientId,
        public array $scopes,
        public \DateTimeImmutable $grantedAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
