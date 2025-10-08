<?php

declare(strict_types=1);

namespace App\Application\User\CreateUser;

final readonly class CreateUserCommand
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $email,
        public string $plainPassword,
        public array $roles = ['ROLE_USER'],
        public bool $isTwoFactorEnabled = false,
        public ?string $totpSecret = null,
        public ?string $userId = null,
    ) {
    }
}
