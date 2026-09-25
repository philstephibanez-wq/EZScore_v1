<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Song\SongPublicationMailJob;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class SongPublicationWorker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
        private readonly SongPublicationMailer $mailer,
    ) {}

    public function processOne(): bool
    {
        $job = $this->claimNext();
        if (!$job instanceof SongPublicationMailJob) {
            return false;
        }

        try {
            $this->mailer->process($job);
        } catch (\Throwable $exception) {
            $job->releaseWithError($exception->getMessage());
            $this->em->flush();
        }

        return true;
    }

    private function claimNext(): ?SongPublicationMailJob
    {
        $staleBefore = (new \DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $row = $this->connection->fetchAssociative(
            "SELECT id
             FROM song_publication_mail_jobs
             WHERE completed_at IS NULL
               AND (claimed_at IS NULL OR claimed_at < :stale)
               AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
             ORDER BY id ASC
             LIMIT 1",
            ['stale' => $staleBefore, 'now' => $now],
        );

        if ($row === false) {
            return null;
        }

        $id = (int) $row['id'];
        $token = bin2hex(random_bytes(24));
        $claimedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $updated = $this->connection->executeStatement(
            "UPDATE song_publication_mail_jobs
             SET claimed_at = :claimed_at,
                 claim_token = :claim_token,
                 attempts = attempts + 1
             WHERE id = :id
               AND completed_at IS NULL
               AND (claimed_at IS NULL OR claimed_at < :stale)
               AND (next_attempt_at IS NULL OR next_attempt_at <= :now)",
            [
                'claimed_at' => $claimedAt,
                'claim_token' => $token,
                'id' => $id,
                'stale' => $staleBefore,
                'now' => $now,
            ],
        );

        if ($updated !== 1) {
            return null;
        }

        $this->em->clear();

        return $this->em->getRepository(SongPublicationMailJob::class)->findOneBy([
            'claimToken' => $token,
        ]);
    }
}
