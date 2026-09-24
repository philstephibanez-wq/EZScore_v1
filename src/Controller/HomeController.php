<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Localized public landing page: /fr and /en.
     */
    #[Route('', name: 'app_localized_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_catalog');
    }

    /**
     * Backward-compatible dashboard URL.
     * The catalog is now the application home page.
     */
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->redirectToRoute('app_catalog');
    }
}
