<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_preferences', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $locale = (string) $request->request->get('locale', '');
            if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported locale "%s".', $locale));
            }

            $user
                ->setLocale($locale)
                ->setNotifyNewSongs($request->request->getBoolean('notify_new_songs'));

            $request->getSession()->set('_locale', $locale);
            $em->flush();

            $this->addFlash('success', 'profile.saved');
            return $this->redirectToRoute('app_profile', ['_locale' => $locale]);
        }

        return $this->render('profile/index.html.twig', [
            'supported_locales' => User::SUPPORTED_LOCALES,
        ]);
    }
}
