<?php

declare(strict_types=1);

namespace App\Domain\Song;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SongTimelineEvent> */
final class SongTimelineEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongTimelineEvent::class);
    }

    /** @return list<SongTimelineEvent> */
    public function findForSongAndType(Song $song, string $eventType): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.song = :song')
            ->andWhere('e.eventType = :type')
            ->setParameter('song', $song)
            ->setParameter('type', $eventType)
            ->orderBy('e.startMs', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SongTimelineEvent> */
    public function findChordEvents(Song $song): array
    {
        return $this->findForSongAndType($song, SongTimelineEvent::TYPE_CHORD);
    }

    /** @return list<SongTimelineEvent> */
    public function findBeatEvents(Song $song): array
    {
        return $this->findForSongAndType($song, SongTimelineEvent::TYPE_BEAT);
    }
}
