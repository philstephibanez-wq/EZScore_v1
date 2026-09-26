<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RegistrationService
{
    public const ACTIVATION_TTL_SECONDS = 86400;
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserManager $userManager,
    ) {
    }

    /**
     * @return array{user: User, token: string}
     */
    public function register(
        string $displayName,
        string $email,
        string $plainPassword,
        string $locale,
    ): array {
        $email = mb_strtolower(trim($email));

        if ($this->users->findOneBy(['email' => $email]) !== null) {
            throw new \DomainException('registration.email_already_used');
        }

        $user = (new User())
            ->setDisplayName($displayName)
            ->setEmail($email)
            ->setRoles(['ROLE_READER'])
            ->setLocale($locale)
            ->setActive(true);

        $this->userManager->hashPassword($user, $plainPassword);
        $token = $this->issueActivationToken($user);

        $this->em->persist($user);
        $this->em->flush();

        return ['user' => $user, 'token' => $token];
    }

    public function activate(string $rawToken): ?User
    {
        $user = $this->users->findByActivationToken($rawToken);

        if (!$user instanceof User) {
            return null;
        }

        $expiresAt = $user->getActivationExpiresAt();
        if ($expiresAt === null || $expiresAt <= new \DateTimeImmutable()) {
            return null;
        }

        $user->verifyEmail(new \DateTimeImmutable());
        $this->em->flush();

        return $user;
    }

    /**
     * @return array{user: User, token: string}|null
     */
    public function renewActivationForEmail(string $email): ?array
    {
        $user = $this->users->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);

        if (!$user instanceof User || $user->isEmailVerified()) {
            return null;
        }

        $lastRequested = $user->getActivationRequestedAt();
        if (
            $lastRequested !== null
            && $lastRequested->modify('+'.self::RESEND_COOLDOWN_SECONDS.' seconds') > new \DateTimeImmutable()
        ) {
            return null;
        }

        $token = $this->issueActivationToken($user);
        $this->em->flush();

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Administrative resend: bypasses the public resend cooldown.
     *
     * @return array{user: User, token: string}|null
     */
    public function forceRenewActivationForUser(User $user): ?array
    {
        if ($user->isEmailVerified()) {
            return null;
        }

        $token = $this->issueActivationToken($user);
        $this->em->flush();

        return ['user' => $user, 'token' => $token];
    }

    private function issueActivationToken(User $user): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $requestedAt = new \DateTimeImmutable();
        $expiresAt = $requestedAt->modify('+'.self::ACTIVATION_TTL_SECONDS.' seconds');

        $user->requestEmailActivation(
            hash('sha256', $rawToken),
            $requestedAt,
            $expiresAt,
        );

        return $rawToken;
    }
}
