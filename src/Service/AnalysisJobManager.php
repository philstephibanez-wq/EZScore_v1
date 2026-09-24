<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Analysis\AnalysisJob;
use App\Domain\Song\Song;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final class AnalysisJobManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function createQueued(Song $song, User $createdBy, string $kind, array $requestData): AnalysisJob
    {
        $job = new AnalysisJob($song, $createdBy, $kind, $requestData);
        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }
}
