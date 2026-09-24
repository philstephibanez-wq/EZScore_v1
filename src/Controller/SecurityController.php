<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth, UserRepository $users, Request $request): Response
    {
        if ($users->countAll() === 0) {
            return $this->redirectToRoute('app_first_run', ['_locale' => $request->getLocale()]);
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard', ['_locale' => $request->getLocale()]);
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
            'google_enabled' => (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '') !== '',
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by firewall.');
    }
}
