<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Security Controller for authentication endpoints.
 */
final class SecurityController extends AbstractController
{
    /**
     * Admin login endpoint.
     *
     * This endpoint is handled by Symfony's JSON login authenticator.
     * The actual authentication logic is in security.yaml configuration.
     */
    #[Route('/admin/login', name: 'admin_login', methods: ['POST'])]
    public function adminLogin(#[CurrentUser] ?SecurityUser $user): JsonResponse
    {
        if (null === $user) {
            return $this->json([
                'success' => false,
                'message' => 'Missing credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getUserId(),
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    /**
     * Admin logout endpoint.
     */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function adminLogout(): JsonResponse
    {
        // This endpoint is handled by Symfony's logout handler
        // It should never be reached
        throw new \LogicException('This code should never be reached');
    }
}
