<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Analysis\AnalysisJob;
use App\Service\AnalysisJobManager;
use App\Service\AnalysisPayloadContract;
use App\Service\AnalysisWorkerTokenGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/internal/analysis')]
final class AnalysisWorkerController extends AbstractController
{
    public function __construct(
        private readonly AnalysisWorkerTokenGuard $guard,
        private readonly AnalysisJobManager $jobs,
        private readonly AnalysisPayloadContract $contract,
    ) {
    }

    #[Route('/jobs/claim', name: 'internal_analysis_job_claim', methods: ['POST'])]
    public function claim(Request $request): Response
    {
        $this->guard->assertAuthorized($request);

        $job = $this->jobs->claimNextQueued();
        if (!$job instanceof AnalysisJob) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->json($this->contract->workerPayload($job));
    }

    #[Route('/jobs/{id}', name: 'internal_analysis_job_status', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function status(AnalysisJob $job, Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);

        return $this->json([
            'schema_version' => AnalysisPayloadContract::SCHEMA_VERSION,
            'job_id' => $job->getId(),
            'song_id' => $job->getSong()->getId(),
            'kind' => $job->getKind(),
            'status' => $job->getStatus()->value,
            'progress' => $job->getProgress(),
            'error_code' => $job->getErrorCode(),
            'updated_at' => $job->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/jobs/{id}/progress', name: 'internal_analysis_job_progress', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function progress(AnalysisJob $job, Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);

        try {
            $payload = $request->toArray();
            $progress = filter_var(
                $payload['progress'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 100]],
            );

            if ($progress === false) {
                throw new \InvalidArgumentException('progress must be an integer between 0 and 100.');
            }

            $this->jobs->updateProgress($job, $progress);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'job_id' => $job->getId(),
            'status' => $job->getStatus()->value,
            'progress' => $job->getProgress(),
        ]);
    }

    #[Route('/jobs/{id}/complete', name: 'internal_analysis_job_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(AnalysisJob $job, Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);

        try {
            $result = $this->contract->validateResult($request->toArray(), $job);
            $this->jobs->complete($job, $result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'job_id' => $job->getId(),
            'status' => $job->getStatus()->value,
            'progress' => $job->getProgress(),
        ]);
    }

    #[Route('/jobs/{id}/fail', name: 'internal_analysis_job_fail', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function fail(AnalysisJob $job, Request $request): JsonResponse
    {
        $this->guard->assertAuthorized($request);

        try {
            $payload = $request->toArray();
            $errorCode = trim((string) ($payload['error_code'] ?? ''));

            if ($errorCode === '') {
                throw new \InvalidArgumentException('error_code is required.');
            }

            $this->jobs->fail($job, $errorCode);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'job_id' => $job->getId(),
            'status' => $job->getStatus()->value,
            'error_code' => $job->getErrorCode(),
        ]);
    }
}
