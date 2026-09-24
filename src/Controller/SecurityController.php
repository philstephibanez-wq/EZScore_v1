<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\UserRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth, UserRepository $users): Response
    {
        if ($users->countAll() === 0) {
            return $this->redirectToRoute('app_first_run');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
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

    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clients): Response
    {
        if ((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '') === '') {
            $this->addFlash('error', 'auth.google.not_configured');
            return $this->redirectToRoute('app_login');
        }

        return $clients->getClient('google_main')->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }
}
