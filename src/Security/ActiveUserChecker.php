<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('auth.account.disabled');
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('auth.account.email_not_verified');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
