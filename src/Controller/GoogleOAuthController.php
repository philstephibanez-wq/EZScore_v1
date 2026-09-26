<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleOAuthController extends AbstractController
{
    #[Route(
        path: ['fr' => '/fr/connect/google', 'en' => '/en/connect/google'],
        name: 'connect_google_start',
        methods: ['GET'],
    )]
    public function start(ClientRegistry $clients): Response
    {
        if ((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '') === '') {
            $this->addFlash('error', 'auth.google.not_configured');
            return $this->redirectToRoute('app_login');
        }

        return $clients->getClient('google_main')->redirect(
            ['email', 'profile'],
            ['prompt' => 'select_account'],
        );
    }

    // Must remain exactly this URL: it is registered in Google Console.
    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function check(Request $request): Response
    {
        $user = $this->getUser();
        $locale = $user instanceof User
            ? $user->getLocale()
            : (string) $request->getSession()->get('_locale', 'fr');

        if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
            $locale = 'fr';
        }

        return $this->redirectToRoute('app_dashboard', ['_locale' => $locale]);
    }
}
