<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Event\Event;
use App\Domain\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {}

    public function sendInvitation(Event $event, User $user): bool
    {
        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $locale = $user->getLocale();
        $url = $this->urls->generate(
            'app_event_show',
            ['_locale' => $locale, 'id' => $event->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'EZSCORE v1'))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject($this->translator->trans(
                'event.email.subject',
                ['%title%' => $event->getTitle()],
                'event',
                $locale,
            ))
            ->htmlTemplate('emails/event_invitation.html.twig')
            ->textTemplate('emails/event_invitation.txt.twig')
            ->context([
                'event' => $event,
                'recipient' => $user,
                'event_url' => $url,
                'locale' => $locale,
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface) {
            return false;
        }
    }
}
