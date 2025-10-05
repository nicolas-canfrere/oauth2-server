<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class OAuthTokenController
{
    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function __invoke(): void
    {
    }
}
