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
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $routeLocale = $request->attributes->get('_locale');
        if (is_string($routeLocale) && $routeLocale !== '') {
            $this->assertSupported($routeLocale);
            $request->setLocale($routeLocale);
            $request->getSession()->set('_locale', $routeLocale);
            return;
        }

        $user = $this->security->getUser();
        $locale = $user instanceof User
            ? $user->getLocale()
            : (string) $request->getSession()->get('_locale', $this->defaultLocale);

        $this->assertSupported($locale);
        $request->setLocale($locale);
    }

    private function assertSupported(string $locale): void
    {
        if (!in_array($locale, $this->supportedLocales, true)) {
            throw new \LogicException(sprintf(
                'Unsupported locale stored in application data: "%s".',
                $locale,
            ));
        }
    }
}
