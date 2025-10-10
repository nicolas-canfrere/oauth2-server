<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

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
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function adminLogin(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Admin logout endpoint.
     */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function adminLogout(): JsonResponse
    {
        // This endpoint is handled by Symfony's logout handler
        // It should never be reached
        throw new \LogicException('This code should never be reached');
    }
}
