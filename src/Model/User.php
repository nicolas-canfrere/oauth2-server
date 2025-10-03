<?php

declare(strict_types=1);

namespace App\Model;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public string $passwordHash,
        public bool $is2faEnabled,
        public ?string $totpSecret,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
