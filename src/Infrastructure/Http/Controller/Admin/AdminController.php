<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('admin', name: 'admin', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/index.html.twig');
    }
}
