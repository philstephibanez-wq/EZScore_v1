<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User\User;
use App\Service\Exception\ActivationMailException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {
    }

    public function sendActivation(User $user, string $rawToken): void
    {
        $this->assertRealMailerConfiguration();

        $locale = $user->getLocale();
        $activationUrl = $this->urls->generate(
            'app_registration_activate',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'EZSCORE v1'))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject($this->translator->trans('registration.email.subject', [], 'registration', $locale))
            ->htmlTemplate('emails/activation.html.twig')
            ->textTemplate('emails/activation.txt.twig')
            ->context([
                'locale' => $locale,
                'activation_url' => $activationUrl,
                'copy' => [
                    'heading' => $this->translator->trans('registration.email.heading', [], 'registration', $locale),
                    'hello' => $this->translator->trans(
                        'registration.email.hello',
                        ['%name%' => $user->getDisplayName()],
                        'registration',
                        $locale,
                    ),
                    'body' => $this->translator->trans('registration.email.body', [], 'registration', $locale),
                    'button' => $this->translator->trans('registration.email.button', [], 'registration', $locale),
                    'expiry' => $this->translator->trans('registration.email.expiry', [], 'registration', $locale),
                    'ignore' => $this->translator->trans('registration.email.ignore', [], 'registration', $locale),
                ],
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new ActivationMailException('Activation email delivery failed.', 0, $exception);
        }
    }

    private function assertRealMailerConfiguration(): void
    {
        $dsn = trim($this->mailerDsn);

        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            throw new ActivationMailException(
                'MAILER_DSN must reference a real mail transport; null transport is forbidden.',
            );
        }

        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new ActivationMailException('MAILER_FROM must be a valid email address.');
        }
    }
}
