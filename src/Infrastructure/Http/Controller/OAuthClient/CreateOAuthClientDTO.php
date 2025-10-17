<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\OAuthClient;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOAuthClientDTO
{
    /**
     * @param list<string> $redirectUris
     * @param list<string> $grantTypes
     * @param list<string> $scopes
     */
    public function __construct(
        #[Assert\NotBlank(message: 'The "name" field is required.')]
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'The name must be at least {{ limit }} character long.',
            maxMessage: 'The name cannot be longer than {{ limit }} characters.'
        )]
        public string $name,
        #[Assert\NotBlank(message: 'The "redirect_uris" field is required.')]
        #[Assert\Count(
            min: 1,
            minMessage: 'At least one redirect URI is required.'
        )]
        #[Assert\All([
            new Assert\NotBlank(message: 'Redirect URI cannot be empty.'),
            new Assert\Url(
                message: 'The value "{{ value }}" is not a valid URL.',
                requireTld: false
            ),
        ])]
        public array $redirectUris = [],
        #[Assert\Count(min: 1, minMessage: 'At least one grant type is required.')]
        #[Assert\All([
            new Assert\NotBlank(message: 'Grant type cannot be empty.'),
            new Assert\Type('string', message: 'Grant type must be a string.'),
        ])]
        public array $grantTypes = ['authorization_code'],
        #[Assert\All([
            new Assert\Type('string', message: 'Scope must be a string.'),
        ])]
        public array $scopes = [],
        #[Assert\Type('bool', message: 'The "is_confidential" field must be a boolean.')]
        public bool $isConfidential = false,
        #[Assert\Type('bool', message: 'The "pkce_required" field must be a boolean.')]
        public bool $pkceRequired = true,
        #[Assert\Uuid(message: 'The client_id "{{ value }}" is not a valid UUID.')]
        public ?string $clientId = null,
        public ?string $clientSecret = null,
    ) {
    }
}
