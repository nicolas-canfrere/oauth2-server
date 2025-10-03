<?php

declare(strict_types=1);

namespace App\Model;

final readonly class OAuthKey
{
    public function __construct(
        public string $id,
        public string $kid,
        public string $algorithm,
        public string $publicKey,
        public string $privateKeyEncrypted,
        public bool $isActive,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
