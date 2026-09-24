<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Service\UserDeletionService;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_users', methods: ['GET', 'POST'])]
    public function index(Request $request, UserRepository $users, UserManager $manager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_user', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('display_name'));
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role', 'ROLE_READER');
            $locale = (string) $request->request->get('locale', 'fr');

            $errors = $this->validateIdentity($name, $email);
            if ($users->findOneBy(['email' => $email]) !== null) $errors[] = 'users.error.email_used';
            if ($password !== '' && mb_strlen($password) < 10) $errors[] = 'users.error.password_length';
            if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported locale "%s".', $locale));
            }

            if ($errors === []) {
                $manager->create($name, $email, $password !== '' ? $password : null, $role, $locale);
                $this->addFlash('success', 'users.created');
                return $this->redirectToRoute('admin_users');
            }

            foreach ($errors as $error) $this->addFlash('error', $error);
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['displayName' => 'ASC']),
            'supported_locales' => User::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/{id}/update', name: 'admin_user_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(User $user, Request $request, UserRepository $users, UserManager $manager): Response
    {
        if (!$this->isCsrfTokenValid('user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('display_name'));
        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $password = (string) $request->request->get('password');
        $role = (string) $request->request->get('role', 'ROLE_READER');
        $locale = (string) $request->request->get('locale', 'fr');
        $active = $request->request->getBoolean('active');

        $errors = $this->validateIdentity($name, $email);
        $emailOwner = $users->findOneBy(['email' => $email]);
        if ($emailOwner !== null && $emailOwner->getId() !== $user->getId()) $errors[] = 'users.error.email_used';
        if ($password !== '' && mb_strlen($password) < 10) $errors[] = 'users.error.password_length_new';
        if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported locale "%s".', $locale));
        }

        $wasActiveAdmin = $user->isActive() && $user->getPrimaryRole() === 'ROLE_ADMIN';
        $willRemainActiveAdmin = $active && $role === 'ROLE_ADMIN';
        if ($wasActiveAdmin && !$willRemainActiveAdmin && $users->countActiveAdmins() <= 1) {
            $errors[] = 'users.error.last_admin';
        }

        if ($errors !== []) {
            foreach ($errors as $error) $this->addFlash('error', $error);
            return $this->redirectToRoute('admin_users');
        }

        $actor = $this->getUser();

        $manager->update(
            $user, $name, $email, $role, $active,
            $password !== '' ? $password : null,
            $locale
        );

        if ($actor instanceof User && $actor->getId() === $user->getId()) {
            $request->getSession()->set('_locale', $locale);
            $request->setLocale($locale);
        }

        $this->addFlash('success', 'users.updated');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, UserDeletionService $deletion): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) throw $this->createAccessDeniedException();

        try {
            $deletion->delete($user, $actor);
            $this->addFlash('success', 'users.deleted');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }

    private function validateIdentity(string $name, string $email): array
    {
        $errors = [];
        if ($name === '') $errors[] = 'users.error.name';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'users.error.email';
        return $errors;
    }
}
