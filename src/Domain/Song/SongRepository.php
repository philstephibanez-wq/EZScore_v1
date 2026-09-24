<?php

declare(strict_types=1);

namespace App\Domain\Song;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Song::class);
    }

    /**
     * @return list<Song>
     */
    public function findCatalog(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.editor', 'e')
            ->addSelect('e')
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
