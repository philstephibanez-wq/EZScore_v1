<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Analysis\AnalysisJobStatus;
use App\Domain\Song\Song;
use App\Domain\Song\UserSongStemMix;
use App\Domain\Song\UserSongStemMixRepository;
use App\Domain\User\User;
use App\Service\AnalysisDesktopStateStore;
use App\Service\SongStemJobService;
use App\Service\SongStemStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/song/{id}/stems', requirements: ['id' => '\d+'])]
final class SongStemController extends AbstractController
{
    #[Route('', name: 'app_song_stems', methods: ['GET'])]
    public function show(
        Song $song,
        SongStemJobService $jobs,
        SongStemStorage $storage,
        AnalysisDesktopStateStore $workerState,
        UserSongStemMixRepository $mixes,
    ): Response {
        $user = $this->requireEditor($song);

        $latest = $jobs->latest($song);
        $progress = $storage->progress($song);
        $manifest = $storage->manifest($song);
        $complete = $storage->hasCompleteStems($song);
        $mix = $mixes->findForUserAndSong($user, $song);

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
            'analysis_worker_online' => $workerState->isOnline(),
            'stem_mix_settings' => $mix?->getSettings() ?? [],
        ]);
    }

    #[Route('/generate', name: 'app_song_stems_generate', methods: ['POST'])]
    public function generate(
        Song $song,
        Request $request,
        SongStemJobService $jobs,
        AnalysisDesktopStateStore $workerState,
    ): Response {
        $user = $this->requireEditor($song);

        if (!$this->isCsrfTokenValid(
            'song_stems_generate_'.$song->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        if (!$workerState->isOnline()) {
            $this->addFlash('error', 'stems.error.worker_offline');

            return $this->redirectToRoute('app_song_stems', [
                '_locale' => $request->getLocale(),
                'id' => $song->getId(),
            ]);
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

    #[Route('/mix', name: 'app_song_stems_mix', methods: ['POST'])]
    public function saveMix(
        Song $song,
        Request $request,
        UserSongStemMixRepository $mixes,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->requireEditor($song);
        $payload = $request->toArray();

        if (!$this->isCsrfTokenValid(
            'song_stems_mix_'.$song->getId(),
            (string) ($payload['_token'] ?? ''),
        )) {
            return $this->json(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $settings = $payload['settings'] ?? null;
        if (!is_array($settings)) {
            return $this->json(['error' => 'invalid_settings'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sanitized = $this->sanitizeMixSettings($settings);

        $mix = $mixes->findForUserAndSong($user, $song) ?? new UserSongStemMix($user, $song);
        $mix->setSettings($sanitized);

        $em->persist($mix);
        $em->flush();

        return $this->json([
            'ok' => true,
            'settings' => $mix->getSettings(),
            'updated_at' => $mix->getUpdatedAt()->format(DATE_ATOM),
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

    #[Route('/original', name: 'app_song_stems_original_audio', methods: ['GET'])]
    public function originalAudio(
        Song $song,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ): Response {
        $this->requireEditor($song);

        $storagePath = trim((string) $song->getAudioStoragePath());
        if ($storagePath === '') {
            throw $this->createNotFoundException();
        }

        // SongImportStorage persists a project-relative path:
        // var/storage/audio/<sha256>.<ext>
        $relativePath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            ltrim($storagePath, '/\\'),
        );

        $path = $projectDir.DIRECTORY_SEPARATOR.$relativePath;

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $realProject = realpath($projectDir);
        $realPath = realpath($path);

        if ($realProject === false || $realPath === false) {
            throw $this->createNotFoundException();
        }

        $projectPrefix = rtrim($realProject, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!str_starts_with($realPath, $projectPrefix)) {
            throw $this->createAccessDeniedException();
        }

        $response = new BinaryFileResponse($realPath);
        $response->headers->set('Content-Type', $song->getAudioMimeType() ?: 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $song->getAudioOriginalName() ?: 'original-audio',
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

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function sanitizeMixSettings(array $settings): array
    {
        $allowedTracks = [
            'original',
            'vocals',
            'lead_vocals',
            'backing_vocals',
            'drums',
            'bass',
            'guitar',
            'piano',
            'other',
        ];

        $tracks = [];
        $rawTracks = is_array($settings['tracks'] ?? null) ? $settings['tracks'] : [];

        foreach ($allowedTracks as $track) {
            $raw = is_array($rawTracks[$track] ?? null) ? $rawTracks[$track] : [];

            $tracks[$track] = [
                'enabled' => (bool) ($raw['enabled'] ?? ($track === 'original')),
                'volume' => $this->clampFloat($raw['volume'] ?? ($track === 'original' ? 1.0 : 0.72), 0.0, 1.25),
                'low' => $this->clampFloat($raw['low'] ?? 0.0, -12.0, 12.0),
                'mid' => $this->clampFloat($raw['mid'] ?? 0.0, -12.0, 12.0),
                'high' => $this->clampFloat($raw['high'] ?? 0.0, -12.0, 12.0),
            ];
        }

        return [
            'schema_version' => 'ezscore.stem_mix.v1',
            'master_volume' => $this->clampFloat($settings['master_volume'] ?? 1.0, 0.0, 1.25),
            'playback_rate' => $this->clampFloat($settings['playback_rate'] ?? 1.0, 0.75, 1.25),
            'tracks' => $tracks,
        ];
    }

    private function clampFloat(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return max($min, min($max, $number));
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
