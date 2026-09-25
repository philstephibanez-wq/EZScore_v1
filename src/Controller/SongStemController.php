<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Analysis\AnalysisJobStatus;
use App\Domain\Song\Song;
use App\Domain\User\User;
use App\Service\SongStemJobService;
use App\Service\SongStemStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/song/{id}/stems', requirements: ['id' => '\d+'])]
final class SongStemController extends AbstractController
{
    #[Route('', name: 'app_song_stems', methods: ['GET'])]
    public function show(
        Song $song,
        SongStemJobService $jobs,
        SongStemStorage $storage,
    ): Response {
        $this->requireEditor($song);

        $latest = $jobs->latest($song);
        $progress = $storage->progress($song);
        $manifest = $storage->manifest($song);
        $complete = $storage->hasCompleteStems($song);

        $isActive = $latest !== null && in_array(
            $latest->getStatus(),
            [AnalysisJobStatus::Queued, AnalysisJobStatus::Running],
            true,
        );

        return $this->render('stems/index.html.twig', [
            'song' => $song,
            'latest_job' => $latest,
            'progress' => $progress,
            'manifest' => $manifest,
            'stems_complete' => $complete,
            'stem_names' => SongStemStorage::STEMS,
            'job_active' => $isActive,
        ]);
    }

    #[Route('/generate', name: 'app_song_stems_generate', methods: ['POST'])]
    public function generate(
        Song $song,
        Request $request,
        SongStemJobService $jobs,
    ): Response {
        $user = $this->requireEditor($song);

        if (!$this->isCsrfTokenValid(
            'song_stems_generate_'.$song->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        if (!$song->getAudioStoragePath() || !$song->getAudioSha256()) {
            $this->addFlash('error', 'stems.error.no_audio');

            return $this->redirectToRoute('app_song_stems', [
                '_locale' => $request->getLocale(),
                'id' => $song->getId(),
            ]);
        }

        $force = $request->request->getBoolean('force');
        $job = $jobs->queue($song, $user, $force);

        $this->addFlash(
            'success',
            $job->getStatus() === AnalysisJobStatus::Queued
                ? 'stems.queued'
                : 'stems.already_running',
        );

        return $this->redirectToRoute('app_song_stems', [
            '_locale' => $request->getLocale(),
            'id' => $song->getId(),
        ]);
    }

    #[Route('/log', name: 'app_song_stems_log', methods: ['GET'])]
    public function log(
        Song $song,
        SongStemStorage $storage,
        SongStemJobService $jobs,
    ): JsonResponse {
        $this->requireEditor($song);

        $latest = $jobs->latest($song);

        return $this->json([
            'exists' => $storage->logExists($song),
            'content' => $storage->readLogTail($song),
            'progress' => $storage->progress($song),
            'job_id' => $latest?->getId(),
            'job_status' => $latest?->getStatus()->value,
            'stems_complete' => $storage->hasCompleteStems($song),
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/log/download', name: 'app_song_stems_log_download', methods: ['GET'])]
    public function downloadLog(
        Song $song,
        SongStemStorage $storage,
    ): Response {
        $this->requireEditor($song);

        $path = $storage->logPath($song);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('ezscore-stems-song-%d.log', (int) $song->getId()),
        );

        return $response;
    }

    #[Route('/audio/{name}', name: 'app_song_stem_audio', methods: ['GET'])]
    public function audio(
        Song $song,
        string $name,
        SongStemStorage $storage,
    ): Response {
        $this->requireEditor($song);

        $path = $storage->stemPath($song, $name);
        if ($path === null) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'audio/wav');
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $name.'.wav',
        );

        return $response;
    }

    private function requireEditor(Song $song): User
    {
        $user = $this->getUser();

        $allowed = $user instanceof User && (
            $this->isGranted('ROLE_ADMIN')
            || (
                $this->isGranted('ROLE_EDITOR')
                && $song->getEditor()?->getId() === $user->getId()
            )
        );

        if (!$allowed) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
