<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\UserRepository;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FirstRunController extends AbstractController
{
    #[Route('/first-run', name: 'app_first_run', methods: ['GET', 'POST'])]
    public function index(Request $request, UserRepository $users, UserManager $manager): Response
    {
        if ($users->countAll() > 0) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('first_run', (string) $request->request->get('_token'))) {
                $errors[] = 'auth.first_run.error.session';
            }

            $name = trim((string) $request->request->get('display_name'));
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('confirm_password');

            if ($name === '') $errors[] = 'auth.first_run.error.name';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'auth.first_run.error.email';
            if (mb_strlen($password) < 10) $errors[] = 'auth.first_run.error.password_length';
            if ($password !== $confirm) $errors[] = 'auth.first_run.error.password_match';

            if ($errors === []) {
                $manager->create($name, $email, $password, 'ROLE_ADMIN');
                $this->addFlash('success', 'auth.first_run.created');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/first_run.html.twig', ['errors' => $errors]);
    }
}
