<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function create(string $name, string $email, ?string $plainPassword, string $role): User
    {
        $user = (new User())
            ->setDisplayName($name)
            ->setEmail($email)
            ->setRoles([$role]);
        if ($plainPassword !== null && $plainPassword !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        }
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();
    }
}
