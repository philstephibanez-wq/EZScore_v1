<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RootController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, UserRepository $users): Response
    {
        $user = $this->getUser();

        $locale = $user instanceof User
            ? $user->getLocale()
            : (string) $request->getSession()->get('_locale', 'fr');

        if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
            $locale = 'fr';
        }

        if ($users->countAll() === 0) {
            return $this->redirectToRoute('app_first_run', ['_locale' => $locale]);
        }

        return $this->redirectToRoute('app_catalog', ['_locale' => $locale]);
    }
}
