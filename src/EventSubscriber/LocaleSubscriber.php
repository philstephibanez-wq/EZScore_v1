<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
        #[Autowire('%app.supported_locales%')]
        private readonly array $supportedLocales,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 18],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $locale = $user->getLocale();
        } else {
            $locale = (string) $request->getSession()->get('_locale', $this->defaultLocale);
        }

        if (!in_array($locale, $this->supportedLocales, true)) {
            throw new \LogicException(sprintf('Unsupported locale stored in application data: "%s".', $locale));
        }

        $request->setLocale($locale);
    }
}
