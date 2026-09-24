<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function findCatalog(?string $query = null): array
    {
        $qb = $this->baseCatalogQuery();
        $this->applySearch($qb, $query);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Song>
     */
    public function findPublished(?string $query = null): array
    {
        $qb = $this->baseCatalogQuery()
            ->andWhere('s.publishedAt IS NOT NULL');
        $this->applySearch($qb, $query);

        return $qb->getQuery()->getResult();
    }

    /**
     * An editor sees every published song plus every song assigned to them.
     *
     * @return list<Song>
     */
    public function findForEditor(User $editor, ?string $query = null): array
    {
        $qb = $this->baseCatalogQuery()
            ->andWhere('(s.publishedAt IS NOT NULL OR s.editor = :editor)')
            ->setParameter('editor', $editor);
        $this->applySearch($qb, $query);

        return $qb->getQuery()->getResult();
    }

    private function baseCatalogQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.editor', 'e')
            ->addSelect('e')
            ->orderBy('s.title', 'ASC')
            ->addOrderBy('s.artist', 'ASC');
    }

    private function applySearch(QueryBuilder $qb, ?string $query): void
    {
        $query = trim((string) $query);
        if ($query === '') {
            return;
        }

        $qb
            ->andWhere(
                $qb->expr()->orX(
                    'LOWER(s.title) LIKE :query',
                    'LOWER(s.artist) LIKE :query',
                    'LOWER(COALESCE(e.displayName, \'\')) LIKE :query',
                ),
            )
            ->setParameter('query', '%' . mb_strtolower($query) . '%');
    }
}
