<?php

declare(strict_types=1);

namespace App\Model;

final readonly class OAuthScope
{
    public function __construct(
        public string $id,
        public string $scope,
        public string $description,
        public bool $isDefault,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
