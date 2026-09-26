<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Song\Song;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/song/{id}/lab', requirements: ['id' => '\\d+'])]
final class SongLabController extends AbstractController
{
    #[Route('/analysis', name: 'app_song_analysis_lab', methods: ['GET'])]
    public function analysis(Song $song): Response
    {
        $this->requireEditor($song);
        return $this->renderLab($song, 'analysis');
    }

    #[Route('/chords', name: 'app_song_chordslab', methods: ['GET'])]
    public function chords(Song $song): Response
    {
        $this->requireEditor($song);
        return $this->renderLab($song, 'chords');
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
