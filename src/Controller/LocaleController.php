<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale', methods: ['POST'])]
    public function change(
        string $locale,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%app.supported_locales%')]
        array $supportedLocales,
    ): RedirectResponse {
        if (!in_array($locale, $supportedLocales, true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('locale', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $request->getSession()->set('_locale', $locale);

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($locale);
            $em->flush();
        }

        $target = (string) $request->headers->get('referer', '');
        if ($target === '') {
            return $this->redirectToRoute($user instanceof User ? 'app_dashboard' : 'app_login');
        }

        return new RedirectResponse($target);
    }
}
