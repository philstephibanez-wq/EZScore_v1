<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogController extends AbstractController
{
    #[Route('/catalog', name: 'app_catalog', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SongRepository $songs,
        UserRepository $users,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $canCreate = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_EDITOR');

        if ($request->isMethod('POST')) {
            if (!$canCreate) {
                throw $this->createAccessDeniedException();
            }

            if (!$this->isCsrfTokenValid('create_song', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $title = trim((string) $request->request->get('title'));
            $artist = trim((string) $request->request->get('artist'));

            if ($title === '' || $artist === '') {
                $this->addFlash('error', 'catalog.validation.required');
                return $this->redirectToRoute('app_catalog');
            }

            $song = (new Song())
                ->setTitle($title)
                ->setArtist($artist)
                ->setComment((string) $request->request->get('comment'));

            if ($this->isGranted('ROLE_ADMIN')) {
                $editorId = (int) $request->request->get('editor_id');
                if ($editorId > 0) {
                    $editor = $users->find($editorId);
                    if (!$editor instanceof User || !in_array('ROLE_EDITOR', $editor->getRoles(), true)) {
                        $this->addFlash('error', 'catalog.validation.editor');
                        return $this->redirectToRoute('app_catalog');
                    }
                    $song->setEditor($editor);
                }
            } elseif ($this->getUser() instanceof User) {
                $song->setEditor($this->getUser());
            }

            $em->persist($song);
            $em->flush();

            $this->addFlash('success', 'catalog.created');
            return $this->redirectToRoute('app_song_workspace', ['id' => $song->getId()]);
        }

        return $this->render('catalog/index.html.twig', [
            'songs' => $songs->findCatalog(),
            'can_create' => $canCreate,
            'editors' => $this->isGranted('ROLE_ADMIN')
                ? $users->createQueryBuilder('u')
                    ->andWhere('u.active = :active')
                    ->andWhere('u.roles LIKE :role')
                    ->setParameter('active', true)
                    ->setParameter('role', '%ROLE_EDITOR%')
                    ->orderBy('u.displayName', 'ASC')
                    ->getQuery()
                    ->getResult()
                : [],
        ]);
    }

    #[Route('/song/{id}', name: 'app_song_workspace', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function song(Song $song): Response
    {
        $canEdit = $this->isGranted('ROLE_ADMIN')
            || ($this->isGranted('ROLE_EDITOR')
                && $song->getEditor()?->getId() === $this->getUser()?->getId());

        return $this->render('song/workspace.html.twig', [
            'song' => $song,
            'can_edit' => $canEdit,
        ]);
    }
}
