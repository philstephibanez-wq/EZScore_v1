<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clients,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clients->getClient('google_main');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $sub = (string) $googleUser->getId();
        $avatar = method_exists($googleUser, 'getAvatar') ? $googleUser->getAvatar() : null;

        $user = $this->users->findByGoogleSub($sub) ?? $this->users->findOneBy(['email' => $email]);

        if ($user === null) {
            throw new CustomUserMessageAuthenticationException('auth.account.google_not_registered');
        }
        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('auth.account.disabled');
        }
        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAuthenticationException('auth.account.email_not_verified');
        }

        if ($user->getGoogleSub() === null) {
            $user->setGoogleSub($sub);
        }
        if (is_string($avatar) && $avatar !== '') {
            $user->setAvatarUrl($avatar);
        }

        $this->em->flush();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user),
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return new RedirectResponse($this->urls->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,
    ): ?Response {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());

        return new RedirectResponse($this->urls->generate('app_login'));
    }
}
