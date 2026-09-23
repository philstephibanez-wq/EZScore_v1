<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        if ($users->countAll() === 0) return $this->redirectToRoute('app_first_run');
        if (!$this->getUser()) return $this->redirectToRoute('app_login');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }
}
