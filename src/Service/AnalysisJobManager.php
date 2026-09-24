<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Analysis\AnalysisJob;
use App\Domain\Analysis\AnalysisJobRepository;
use App\Domain\Analysis\AnalysisJobStatus;
use App\Domain\Song\Song;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final class AnalysisJobManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AnalysisJobRepository $jobs,
    ) {
    }

    public function createQueued(Song $song, User $createdBy, string $kind, array $requestData): AnalysisJob
    {
        $job = new AnalysisJob($song, $createdBy, $kind, $requestData);
        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    public function claimNextQueued(): ?AnalysisJob
    {
        $job = $this->jobs->findNextQueued();
        if (!$job instanceof AnalysisJob) {
            return null;
        }

        $job->markRunning(0);
        $this->em->flush();

        return $job;
    }

    public function updateProgress(AnalysisJob $job, int $progress): AnalysisJob
    {
        if ($job->getStatus() !== AnalysisJobStatus::Running) {
            throw new \DomainException('Only a running analysis job can report progress.');
        }

        $job->setProgress($progress);
        $this->em->flush();

        return $job;
    }

    public function complete(AnalysisJob $job, array $resultData): AnalysisJob
    {
        if ($job->getStatus() !== AnalysisJobStatus::Running) {
            throw new \DomainException('Only a running analysis job can be completed.');
        }

        $job->complete($resultData);
        $this->em->flush();

        return $job;
    }

    public function fail(AnalysisJob $job, string $errorCode): AnalysisJob
    {
        if (in_array($job->getStatus(), [
            AnalysisJobStatus::Completed,
            AnalysisJobStatus::Cancelled,
        ], true)) {
            throw new \DomainException('A completed or cancelled analysis job cannot be failed.');
        }

        $job->fail($errorCode);
        $this->em->flush();

        return $job;
    }
}
