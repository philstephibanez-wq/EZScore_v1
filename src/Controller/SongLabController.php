<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Song\Song;
use App\Domain\Song\SongTimelineEvent;
use App\Domain\Song\SongTimelineEventRepository;
use App\Domain\Song\UserSongStemMixRepository;
use App\Domain\User\User;
use App\Service\ChordTimelineAnalysisService;
use App\Service\SongStemPlaybackStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/song/{id}/lab', requirements: ['id' => '\d+'])]
final class SongLabController extends AbstractController
{
    private const TIME_SIGNATURES = [
        'auto',
        '2/2', '2/4',
        '3/2', '3/4', '3/8',
        '4/2', '4/4', '4/8',
        '5/4', '5/8',
        '6/4', '6/8',
        '7/4', '7/8',
        '8/8',
        '9/8',
        '10/8',
        '11/8',
        '12/8', '12/16',
        '13/8', '13/16',
        '14/8',
        '15/8', '15/16',
        '16/8',
    ];

    #[Route('/analysis', name: 'app_song_analysis_lab', methods: ['GET'])]
    public function analysis(Song $song): Response
    {
        $this->requireEditor($song);
        return $this->renderLab($song, 'analysis');
    }

    #[Route('/chords', name: 'app_song_chordslab', methods: ['GET'])]
    public function chords(
        Song $song,
        SongTimelineEventRepository $timeline,
        UserSongStemMixRepository $mixes,
        SongStemPlaybackStorage $playback,
    ): Response {
        $user = $this->requireEditor($song);

        return $this->render('song/chordslab.html.twig', [
            'song' => $song,
            'chord_events' => array_map(
                static fn (SongTimelineEvent $event): array => [
                    'id' => $event->getId(),
                    'start_ms' => $event->getStartMs(),
                    'end_ms' => $event->getEndMs(),
                    'measure_index' => $event->getMeasureIndex(),
                    'beat_index' => $event->getBeatIndex(),
                    'subdivision_index' => $event->getSubdivisionIndex(),
                    'original' => $event->getOriginalValue(),
                    'override' => $event->getOverrideValue(),
                    'effective' => $event->getEffectiveValue(),
                ],
                $timeline->findChordEvents($song),
            ),
            'beat_events' => array_map(
                static fn (SongTimelineEvent $event): array => [
                    'id' => $event->getId(),
                    'start_ms' => $event->getStartMs(),
                    'measure_index' => $event->getMeasureIndex(),
                    'beat_index' => $event->getBeatIndex(),
                    'subdivision_index' => $event->getSubdivisionIndex(),
                ],
                $timeline->findBeatEvents($song),
            ),
            'time_signatures' => self::TIME_SIGNATURES,
            'stem_mix_settings' => $mixes->findForUserAndSong($user, $song)?->getSettings() ?? [],
            'playback_ready' => $playback->isReady($song),
        ]);
    }


    #[Route('/chords/analyze', name: 'app_song_chordslab_analyze', methods: ['POST'])]
    public function analyzeChords(
        Song $song,
        Request $request,
        ChordTimelineAnalysisService $analysis,
        SongTimelineEventRepository $timeline,
        EntityManagerInterface $em,
    ): Response {
        $this->requireEditor($song);

        if (!$this->isCsrfTokenValid('song_chordslab_analyze_'.$song->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $overrides = [];
        foreach ($timeline->findChordEvents($song) as $existing) {
            if ($existing->getOverrideValue() !== null) {
                $key = ($existing->getMeasureIndex() ?? -1).':'.($existing->getBeatIndex() ?? -1);
                $overrides[$key] = $existing->getOverrideValue();
            }
        }

        try {
            $result = $analysis->analyze($song);
            $timeline->deleteMusicalAnalysisForSong($song);

            foreach (($result['beats'] ?? []) as $beat) {
                if (!is_array($beat)) continue;
                $event=(new SongTimelineEvent($song, SongTimelineEvent::TYPE_BEAT, max(0,(int)($beat['start_ms']??0))))
                    ->setPosition(
                        isset($beat['measure_index'])?(int)$beat['measure_index']:null,
                        isset($beat['beat_index'])?(int)$beat['beat_index']:null,
                        isset($beat['subdivision_index'])?(int)$beat['subdivision_index']:null,
                    );
                $em->persist($event);
            }

            foreach (($result['chords'] ?? []) as $chord) {
                if (!is_array($chord)) continue;
                $measure=isset($chord['measure_index'])?(int)$chord['measure_index']:null;
                $beat=isset($chord['beat_index'])?(int)$chord['beat_index']:null;
                $event=(new SongTimelineEvent($song, SongTimelineEvent::TYPE_CHORD, max(0,(int)($chord['start_ms']??0))))
                    ->setPosition($measure,$beat,isset($chord['subdivision_index'])?(int)$chord['subdivision_index']:null)
                    ->setOriginalValue(trim((string)($chord['chord']??'.')))
                    ->setPayload([
                        'confidence'=>isset($chord['confidence'])?(float)$chord['confidence']:null,
                        'analysis_level'=>$song->getChordAnalysisLevel(),
                        'analysis_version'=>(string)($result['version']??'r33'),
                    ]);

                $overrideKey=($measure??-1).':'.($beat??-1);
                if (isset($overrides[$overrideKey])) $event->setOverrideValue($overrides[$overrideKey]);
                $em->persist($event);
            }

            $key=trim((string)($result['key']??''));
            if ($key!=='') $song->setKeySignature($key);

            if ($song->getTimeSignature()==='auto') {
                $sig=trim((string)($result['time_signature']??''));
                if ($sig!=='') $song->setTimeSignature($sig);
            }

            $em->flush();
            $this->addFlash('success','chordslab.analyze.done');
        } catch (\Throwable $error) {
            $this->addFlash('error','chordslab.analyze.failed|'.mb_substr($error->getMessage(),0,240));
        }

        return $this->redirectToRoute('app_song_chordslab', ['_locale'=>$request->getLocale(),'id'=>$song->getId()]);
    }

    #[Route('/chords/settings', name: 'app_song_chordslab_settings', methods: ['POST'])]
    public function saveChordSettings(
        Song $song,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->requireEditor($song);

        if (!$this->isCsrfTokenValid(
            'song_chordslab_settings_'.$song->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        $capo = (int) $request->request->get('capo', 0);
        $signature = trim((string) $request->request->get('time_signature', '4/4'));
        $level = trim((string) $request->request->get('chord_analysis_level', 'intermediate'));

        try {
            $song
                ->setCapo($capo)
                ->setTimeSignature($signature)
                ->setChordAnalysisLevel($level);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'chordslab.settings.invalid');

            return $this->redirectToRoute('app_song_chordslab', [
                '_locale' => $request->getLocale(),
                'id' => $song->getId(),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'chordslab.settings.saved');

        return $this->redirectToRoute('app_song_chordslab', [
            '_locale' => $request->getLocale(),
            'id' => $song->getId(),
        ]);
    }

    #[Route('/chords/event/{eventId}', name: 'app_song_chordslab_event', requirements: ['eventId' => '\d+'], methods: ['POST'])]
    public function saveChordOverride(
        Song $song,
        int $eventId,
        Request $request,
        SongTimelineEventRepository $timeline,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->requireEditor($song);

        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid(
            'song_chordslab_edit_'.$song->getId(),
            (string) ($payload['_token'] ?? ''),
        )) {
            return $this->json(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $event = $timeline->find($eventId);
        if (!$event instanceof SongTimelineEvent
            || $event->getSong()->getId() !== $song->getId()
            || $event->getEventType() !== SongTimelineEvent::TYPE_CHORD) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $chord = trim((string) ($payload['chord'] ?? ''));
        if ($chord === '' || mb_strlen($chord) > 32 || !preg_match('/^[A-G](?:#|b)?[A-Za-z0-9()+#b°øΔ\/-]*$/u', $chord)) {
            return $this->json(['error' => 'invalid_chord'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $event->setOverrideValue($chord);
        $em->flush();

        return $this->json([
            'ok' => true,
            'id' => $event->getId(),
            'original' => $event->getOriginalValue(),
            'override' => $event->getOverrideValue(),
            'effective' => $event->getEffectiveValue(),
        ]);
    }

    #[Route('/chords/reset', name: 'app_song_chordslab_reset', methods: ['POST'])]
    public function resetChordOverrides(
        Song $song,
        Request $request,
        SongTimelineEventRepository $timeline,
        EntityManagerInterface $em,
    ): Response {
        $this->requireEditor($song);

        if (!$this->isCsrfTokenValid(
            'song_chordslab_reset_'.$song->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        foreach ($timeline->findChordEvents($song) as $event) {
            $event->resetOverride();
        }

        $em->flush();
        $this->addFlash('success', 'chordslab.reset.done');

        return $this->redirectToRoute('app_song_chordslab', [
            '_locale' => $request->getLocale(),
            'id' => $song->getId(),
        ]);
    }

    #[Route('/lyrics', name: 'app_song_lyricslab', methods: ['GET'])]
    public function lyrics(Song $song): Response
    {
        $this->requireEditor($song);
        return $this->renderLab($song, 'lyrics');
    }

    #[Route('/publication', name: 'app_song_publication_lab', methods: ['GET'])]
    public function publication(Song $song): Response
    {
        $this->requireEditor($song);
        return $this->renderLab($song, 'publication');
    }

    private function renderLab(Song $song, string $lab): Response
    {
        return $this->render('song/lab_placeholder.html.twig', [
            'song' => $song,
            'lab' => $lab,
        ]);
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
