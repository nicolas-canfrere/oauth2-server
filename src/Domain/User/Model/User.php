<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

final readonly class User
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $passwordHash,
        public bool $is2faEnabled,
        public ?string $totpSecret,
        public array $roles,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
