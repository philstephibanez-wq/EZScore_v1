<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SongRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongRating::class);
    }

    /**
     * @param list<Song> $songs
     * @return array<int, array{average: float, count: int}>
     */
    public function summariesForSongs(array $songs): array
    {
        $ids = array_values(array_filter(
            array_map(static fn (Song $song): ?int => $song->getId(), $songs),
            static fn (?int $id): bool => $id !== null,
        ));

        if ($ids === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('rating')
            ->select('IDENTITY(rating.song) AS song_id')
            ->addSelect('AVG(rating.rating) AS rating_average')
            ->addSelect('COUNT(rating.id) AS rating_count')
            ->andWhere('rating.song IN (:song_ids)')
            ->setParameter('song_ids', $ids)
            ->groupBy('rating.song')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['song_id']] = [
                'average' => round((float) $row['rating_average'], 1),
                'count' => (int) $row['rating_count'],
            ];
        }

        return $result;
    }

    /**
     * @return array{average: float, count: int}
     */
    public function summaryForSong(Song $song): array
    {
        $row = $this->createQueryBuilder('rating')
            ->select('AVG(rating.rating) AS rating_average')
            ->addSelect('COUNT(rating.id) AS rating_count')
            ->andWhere('rating.song = :song')
            ->setParameter('song', $song)
            ->getQuery()
            ->getSingleResult();

        return [
            'average' => (int) $row['rating_count'] > 0
                ? round((float) $row['rating_average'], 1)
                : 0.0,
            'count' => (int) $row['rating_count'],
        ];
    }

    public function findForUserAndSong(User $user, Song $song): ?SongRating
    {
        return $this->findOneBy([
            'user' => $user,
            'song' => $song,
        ]);
    }
}
