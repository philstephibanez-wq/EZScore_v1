<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManager
{
    public const ROLES = ['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_READER'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * Creates an administrator-managed account.
     * The administrator explicitly vouches for the address, so the account is
     * created as email-verified.
     */
    public function create(string $name, string $email, ?string $plainPassword, string $role): User
    {
        $user = (new User())
            ->setDisplayName($name)
            ->setEmail($email)
            ->setRoles([$this->normaliseRole($role)])
            ->markEmailVerifiedForManagedAccount();

        if ($plainPassword !== null && $plainPassword !== '') {
            $this->setPassword($user, $plainPassword);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function update(
        User $user,
        string $name,
        string $email,
        string $role,
        bool $active,
        ?string $plainPassword = null,
    ): void {
        $user
            ->setDisplayName($name)
            ->setEmail($email)
            ->setRoles([$this->normaliseRole($role)])
            ->setActive($active);

        if ($plainPassword !== null && $plainPassword !== '') {
            $this->setPassword($user, $plainPassword);
        }

        $this->em->flush();
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $this->setPassword($user, $plainPassword);
        $this->em->flush();
    }

    public function hashPassword(User $user, string $plainPassword): void
    {
        $this->setPassword($user, $plainPassword);
    }

    private function setPassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
    }

    private function normaliseRole(string $role): string
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported role "%s".', $role));
        }

        return $role;
    }
}
