<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Song\Song;
use App\Domain\Song\SongPublicationMailJob;
use Doctrine\ORM\EntityManagerInterface;

final class SongPublicationQueue
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function enqueue(Song $song): bool
    {
        if (!$song->isPublished()) {
            return false;
        }

        $existing = $this->em->getRepository(SongPublicationMailJob::class)->findOneBy([
            'song' => $song,
        ]);

        if ($existing instanceof SongPublicationMailJob) {
            return false;
        }

        $this->em->persist(new SongPublicationMailJob($song));
        $this->em->flush();

        return true;
    }
}
