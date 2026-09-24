<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AnalysisJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalysisJob::class);
    }

    public function findNextQueued(): ?AnalysisJob
    {
        return $this->createQueryBuilder('job')
            ->andWhere('job.status = :status')
            ->setParameter('status', AnalysisJobStatus::Queued->value)
            ->orderBy('job.createdAt', 'ASC')
            ->addOrderBy('job.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
