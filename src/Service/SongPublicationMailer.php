<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Song\Song;
use App\Domain\Song\SongPublicationMailJob;
use App\Domain\Song\SongPublicationNotification;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SongPublicationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {}

    /**
     * Process one claimed publication job.
     *
     * @return array{eligible:int,sent:int,failed:int,skipped:int}
     */
    public function process(SongPublicationMailJob $job): array
    {
        $song = $job->getSong();
        $stats = ['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        if (!$song->isPublished()) {
            // The song may have been unpublished while waiting in the queue.
            $job->complete();
            $this->em->flush();
            return $stats;
        }

        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('MAILER_FROM is not a valid email address.');
        }

        $recipients = $this->users->createQueryBuilder('u')
            ->andWhere('u.active = true')
            ->andWhere('u.emailVerifiedAt IS NOT NULL')
            ->andWhere('u.notifyNewSongs = true')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($recipients as $recipient) {
            if (!$recipient instanceof User || !filter_var($recipient->getEmail(), FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            ++$stats['eligible'];

            $delivery = $this->em->getRepository(SongPublicationNotification::class)->findOneBy([
                'song' => $song,
                'user' => $recipient,
            ]);

            if ($delivery instanceof SongPublicationNotification && $delivery->wasSent()) {
                ++$stats['skipped'];
                continue;
            }

            if (!$delivery instanceof SongPublicationNotification) {
                $delivery = new SongPublicationNotification($song, $recipient);
                $this->em->persist($delivery);
                $this->em->flush();
            }

            $locale = $recipient->getLocale();
            $songUrl = $this->urls->generate(
                'app_song_workspace',
                ['_locale' => $locale, 'id' => $song->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $profileUrl = $this->urls->generate(
                'app_profile',
                ['_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromAddress, 'EZSCORE v1'))
                ->to(new Address($recipient->getEmail(), $recipient->getDisplayName()))
                ->subject($this->translator->trans(
                    'publication_mail.subject',
                    ['%title%' => $song->getTitle()],
                    'publication_mail',
                    $locale,
                ))
                ->htmlTemplate('emails/song_published.html.twig')
                ->textTemplate('emails/song_published.txt.twig')
                ->context([
                    'song' => $song,
                    'recipient' => $recipient,
                    'song_url' => $songUrl,
                    'profile_url' => $profileUrl,
                    'locale' => $locale,
                ]);

            try {
                $this->mailer->send($email);
                $delivery->markSent();
                ++$stats['sent'];
            } catch (TransportExceptionInterface $exception) {
                $delivery->markFailed($exception->getMessage());
                ++$stats['failed'];
            } catch (\Throwable $exception) {
                $delivery->markFailed($exception->getMessage());
                ++$stats['failed'];
            }

            $this->em->flush();
        }

        // Individual failed deliveries remain retryable: the job is released instead
        // of completed so a later worker pass retries only recipients not marked sent.
        if ($stats['failed'] > 0) {
            throw new \RuntimeException(sprintf('%d publication email(s) failed.', $stats['failed']));
        }

        $job->complete();
        $this->em->flush();

        return $stats;
    }
}
