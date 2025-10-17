<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserDTO
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Assert\NotBlank(message: 'The "email" field is required.')]
        #[Assert\Email(message: 'The email "{{ value }}" is not a valid email address.')]
        public string $email,
        #[Assert\NotBlank(message: 'The "password" field is required.')]
        #[Assert\Length(
            min: 8,
            minMessage: 'The password must be at least {{ limit }} characters long.'
        )]
        public string $password,
        #[Assert\All([
            new Assert\Type('string', message: 'Role must be a string.'),
        ])]
        public array $roles = ['ROLE_USER'],
        #[Assert\Type('bool', message: 'The "is_two_factor_enabled" field must be a boolean.')]
        public bool $isTwoFactorEnabled = false,
        public ?string $totpSecret = null,
    ) {
    }
}
