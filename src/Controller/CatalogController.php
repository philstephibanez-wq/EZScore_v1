<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Song\MockSongProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    #[Route('/catalog', name: 'app_catalog', methods: ['GET'])]
    public function index(MockSongProvider $songs): Response
    {
        return $this->render('catalog/index.html.twig', ['songs' => $songs->catalog()]);
    }

    #[Route('/song/{id}', name: 'app_song_workspace', methods: ['GET'])]
    public function song(string $id, MockSongProvider $songs): Response
    {
        return $this->render('song/workspace.html.twig', [
            'vm' => $songs->workspace($id),
            'can_edit' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_EDITOR'),
        ]);
    }
}
