<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
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
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('create_user', (string) $request->request->get('_token'))) {
            $name = trim((string) $request->request->get('display_name'));
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role', 'ROLE_READER');
            if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $users->findOneBy(['email' => mb_strtolower($email)]) === null) {
                $manager->create($name, $email, $password !== '' ? $password : null, $role);
                $this->addFlash('success', 'Utilisateur créé.');
                return $this->redirectToRoute('admin_users');
            }
            $this->addFlash('error', 'Utilisateur invalide ou e-mail déjà utilisé.');
        }
        return $this->render('admin/users.html.twig', ['users' => $users->findBy([], ['displayName' => 'ASC'])]);
    }

    #[Route('/{id}/update', name: 'admin_user_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $role = (string) $request->request->get('role', 'ROLE_READER');
        $user->setRoles([$role]);
        $user->setActive($request->request->getBoolean('active'));
        $em->flush();
        $this->addFlash('success', 'Utilisateur mis à jour.');
        return $this->redirectToRoute('admin_users');
    }
}
