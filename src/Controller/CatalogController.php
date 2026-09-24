<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    #[Route('/catalog', name: 'app_catalog', methods: ['GET'])]
    public function index(Request $request, SongRepository $songs): Response
    {
        $user = $this->getUser();
        $query = trim((string) $request->query->get('q', ''));

        if ($this->isGranted('ROLE_ADMIN')) {
            $visibleSongs = $songs->findCatalog($query);
        } elseif ($user instanceof User && $this->isGranted('ROLE_EDITOR')) {
            $visibleSongs = $songs->findForEditor($user, $query);
        } else {
            $visibleSongs = $songs->findPublished($query);
        }

        return $this->render('catalog/index.html.twig', [
            'songs' => $visibleSongs,
            'query' => $query,
            'can_import' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_EDITOR'),
            'is_public_catalog' => !$user instanceof User,
        ]);
    }

    #[Route('/song/{id}', name: 'app_song_workspace', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function song(Song $song): Response
    {
        $user = $this->getUser();
        $isOwnerEditor = $user instanceof User
            && $this->isGranted('ROLE_EDITOR')
            && $song->getEditor()?->getId() === $user->getId();

        $canView = $this->isGranted('ROLE_ADMIN')
            || $song->isPublished()
            || $isOwnerEditor;

        if (!$canView) {
            throw $this->createNotFoundException();
        }

        $canEdit = $this->isGranted('ROLE_ADMIN') || $isOwnerEditor;

        $canUseKaraoke = $this->isGranted('ROLE_ADMIN')
            || ($user instanceof User && !$this->isGranted('ROLE_EDITOR') && $song->isPublished())
            || $isOwnerEditor;

        return $this->render('song/workspace.html.twig', [
            'song' => $song,
            'can_edit' => $canEdit,
            'can_publish' => $canEdit,
            'can_play_audio' => $user instanceof User,
            'can_use_karaoke' => $canUseKaraoke,
            'karaoke_preview_seconds' => $user instanceof User ? 0 : 20,
        ]);
    }

    #[Route('/song/{id}/publication', name: 'app_song_publication', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publication(Song $song, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $canEdit = $this->isGranted('ROLE_ADMIN')
            || ($user instanceof User
                && $this->isGranted('ROLE_EDITOR')
                && $song->getEditor()?->getId() === $user->getId());

        if (!$canEdit) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('song_publication_' . $song->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $action = (string) $request->request->get('action');
        if ($action === 'publish') {
            $song->publish();
            $this->addFlash('success', 'song.publication.published');
        } elseif ($action === 'unpublish') {
            $song->unpublish();
            $this->addFlash('success', 'song.publication.unpublished');
        } else {
            throw $this->createNotFoundException();
        }

        $em->flush();

        return $this->redirectToRoute('app_song_workspace', [
            '_locale' => $request->getLocale(),
            'id' => $song->getId(),
        ]);
    }
}
