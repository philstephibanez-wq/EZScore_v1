<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
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

            $errors = $this->validateIdentity($name, $email);
            if ($users->findOneBy(['email' => $email]) !== null) {
                $errors[] = 'Cette adresse e-mail est déjà utilisée.';
            }
            if ($password !== '' && mb_strlen($password) < 10) {
                $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
            }

            if ($errors === []) {
                $manager->create($name, $email, $password !== '' ? $password : null, $role);
                $this->addFlash('success', 'Utilisateur créé.');
                return $this->redirectToRoute('admin_users');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['displayName' => 'ASC']),
        ]);
    }

    #[Route('/{id}/update', name: 'admin_user_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(
        User $user,
        Request $request,
        UserRepository $users,
        UserManager $manager,
    ): Response {
        if (!$this->isCsrfTokenValid('user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('display_name'));
        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $password = (string) $request->request->get('password');
        $role = (string) $request->request->get('role', 'ROLE_READER');
        $active = $request->request->getBoolean('active');

        $errors = $this->validateIdentity($name, $email);

        $emailOwner = $users->findOneBy(['email' => $email]);
        if ($emailOwner !== null && $emailOwner->getId() !== $user->getId()) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        }

        if ($password !== '' && mb_strlen($password) < 10) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
        }

        $wasActiveAdmin = $user->isActive() && $user->getPrimaryRole() === 'ROLE_ADMIN';
        $willRemainActiveAdmin = $active && $role === 'ROLE_ADMIN';

        if ($wasActiveAdmin && !$willRemainActiveAdmin && $users->countActiveAdmins() <= 1) {
            $errors[] = 'Impossible de désactiver ou rétrograder le dernier administrateur actif.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_users');
        }

        $manager->update(
            $user,
            $name,
            $email,
            $role,
            $active,
            $password !== '' ? $password : null,
        );

        $this->addFlash('success', 'Utilisateur mis à jour.');
        return $this->redirectToRoute('admin_users');
    }

    /**
     * @return list<string>
     */
    private function validateIdentity(string $name, string $email): array
    {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Nom affiché obligatoire.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        return $errors;
    }
}
