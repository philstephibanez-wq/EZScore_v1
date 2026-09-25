<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/playlists')]
final class PlaylistController extends AbstractController
{
    #[Route('', name: 'app_playlists', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requireUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_playlist', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'playlists.validation.name');
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }

            $public = ($this->isGranted('ROLE_EDITOR') || $this->isGranted('ROLE_ADMIN'))
                && $request->request->getBoolean('public');

            $playlist = (new Playlist())
                ->setName($name)
                ->setDescription((string) $request->request->get('description'))
                ->setOwnerUser($user)
                ->setCreatedBy($user)
                ->setPublic($public);

            $em->persist($playlist);
            $em->flush();

            $this->addFlash('success', 'playlists.created');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $pendingInvitations = $em->getRepository(PlaylistInvitation::class)->findBy([
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Pending,
        ], ['createdAt' => 'DESC']);

        $acceptedByPlaylist = [];
        foreach ($em->getRepository(PlaylistInvitation::class)->findBy([
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Accepted,
        ]) as $invitation) {
            $acceptedByPlaylist[(int) $invitation->getPlaylist()->getId()] = $invitation;
        }

        $visible = [];
        foreach ($em->getRepository(Playlist::class)->findBy([], ['name' => 'ASC']) as $playlist) {
            if ($this->isGranted(AclPrivilege::PLAYLIST_VIEW, $playlist)) {
                $visible[] = $playlist;
            }
        }

        $itemsByPlaylist = [];
        $invitationsByPlaylist = [];
        $groupsByPlaylist = [];

        foreach ($visible as $playlist) {
            $playlistId = (int) $playlist->getId();

            $itemsByPlaylist[$playlistId] = array_values(array_filter(
                $em->getRepository(PlaylistItem::class)->findBy(
                    ['playlist' => $playlist],
                    ['position' => 'ASC', 'id' => 'ASC'],
                ),
                fn(PlaylistItem $item): bool => $this->isGranted(AclPrivilege::SONG_VIEW, $item->getSong()),
            ));

            $groupsByPlaylist[$playlistId] = array_map(
                static fn(PlaylistGroup $link) => $link->getGroup(),
                $em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist]),
            );

            if ($this->isGranted(AclPrivilege::PLAYLIST_INVITE, $playlist)) {
                $invitationsByPlaylist[$playlistId] = $em->getRepository(PlaylistInvitation::class)->findBy(
                    ['playlist' => $playlist],
                    ['createdAt' => 'DESC'],
                );
            }
        }

        return $this->render('playlists/index.html.twig', [
            'playlists' => $visible,
            'items_by_playlist' => $itemsByPlaylist,
            'groups_by_playlist' => $groupsByPlaylist,
            'pending_invitations' => $pendingInvitations,
            'accepted_invitations_by_playlist' => $acceptedByPlaylist,
            'invitations_by_playlist' => $invitationsByPlaylist,
            'can_publish_playlist' => $this->isGranted('ROLE_EDITOR') || $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/update', name: 'app_playlist_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_EDIT, $playlist);

        if (!$this->isCsrfTokenValid('playlist_update_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'playlists.validation.name');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $public = ($this->isGranted('ROLE_EDITOR') || $this->isGranted('ROLE_ADMIN'))
            && $request->request->getBoolean('public');

        $playlist->setName($name)
            ->setDescription((string) $request->request->get('description'))
            ->setPublic($public);

        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.updated', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/delete', name: 'app_playlist_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_DELETE, $playlist);

        if (!$this->isCsrfTokenValid('playlist_delete_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($playlist);
        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.deleted', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/invitations/{id}/respond', name: 'app_playlist_invitation_respond', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function respondInvitation(
        PlaylistInvitation $invitation,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $user = $this->requireUser();
        if ($invitation->getInvitedUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_invitation_respond_'.$invitation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $action = (string) $request->request->get('action');

        try {
            if ($action === 'accept') {
                $invitation->accept();
                $message = 'playlists.invitation.accepted';
            } elseif ($action === 'decline') {
                $invitation->decline();
                $message = 'playlists.invitation.declined';
            } elseif ($action === 'leave' && $invitation->getStatus() === PlaylistInvitationStatus::Accepted) {
                $invitation->cancel();
                $message = 'playlists.invitation.left';
            } else {
                throw new \DomainException('Invalid playlist invitation action.');
            }
        } catch (\DomainException) {
            throw $this->createAccessDeniedException();
        }

        $em->flush();
        $this->addFlash('success', $translator->trans($message, [], 'management'));

        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/invitations/bulk-add', name: 'app_playlist_invitations_bulk_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkAddInvitations(Playlist $playlist, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_INVITE, $playlist);
        $this->validateAjaxCsrf('playlist_invitations_bulk_'.$playlist->getId(), $request);

        $owner = $this->requireUser();
        $changed = 0;

        foreach ($this->ids($request) as $id) {
            $invited = $em->getRepository(User::class)->find($id);
            if (!$invited instanceof User || !$invited->isActive() || $invited->getId() === $owner->getId()) {
                continue;
            }

            $repo = $em->getRepository(PlaylistInvitation::class);
            $invitation = $repo->findOneBy(['playlist' => $playlist, 'invitedUser' => $invited]);

            if ($invitation instanceof PlaylistInvitation) {
                if (in_array($invitation->getStatus(), [
                    PlaylistInvitationStatus::Pending,
                    PlaylistInvitationStatus::Accepted,
                ], true)) {
                    continue;
                }
                $invitation->reopen($owner);
            } else {
                $em->persist(new PlaylistInvitation($playlist, $invited, $owner));
            }
            ++$changed;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{id}/invitations/bulk-remove', name: 'app_playlist_invitations_bulk_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkRemoveInvitations(Playlist $playlist, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_REVOKE_SHARE, $playlist);
        $this->validateAjaxCsrf('playlist_invitations_bulk_'.$playlist->getId(), $request);

        $changed = 0;
        foreach ($this->ids($request) as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user instanceof User) continue;

            $invitation = $em->getRepository(PlaylistInvitation::class)->findOneBy([
                'playlist' => $playlist,
                'invitedUser' => $user,
            ]);

            if (!$invitation instanceof PlaylistInvitation
                || !in_array($invitation->getStatus(), [
                    PlaylistInvitationStatus::Pending,
                    PlaylistInvitationStatus::Accepted,
                ], true)) {
                continue;
            }

            $invitation->cancel();
            ++$changed;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{id}/songs/bulk-add', name: 'app_playlist_songs_bulk_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkAddSongs(
        Playlist $playlist,
        Request $request,
        EntityManagerInterface $em,
        SongRepository $songs,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_ADD_SONG, $playlist);
        $this->validateAjaxCsrf('playlist_songs_bulk_'.$playlist->getId(), $request);

        $user = $this->requireUser();
        $itemRepo = $em->getRepository(PlaylistItem::class);
        $last = $itemRepo->findOneBy(['playlist' => $playlist], ['position' => 'DESC', 'id' => 'DESC']);
        $position = $last instanceof PlaylistItem ? $last->getPosition() + 1 : 0;
        $changed = 0;

        foreach ($this->ids($request) as $id) {
            $song = $songs->find($id);
            if (!$song instanceof Song || !$this->isGranted(AclPrivilege::SONG_VIEW, $song)) {
                continue;
            }

            if ($itemRepo->findOneBy(['playlist' => $playlist, 'song' => $song]) instanceof PlaylistItem) {
                continue;
            }

            $em->persist(new PlaylistItem($playlist, $song, $user, $position++));
            ++$changed;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{id}/songs/bulk-remove', name: 'app_playlist_songs_bulk_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkRemoveSongs(Playlist $playlist, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_REMOVE_SONG, $playlist);
        $this->validateAjaxCsrf('playlist_songs_bulk_'.$playlist->getId(), $request);

        $changed = 0;
        foreach ($this->ids($request) as $id) {
            $song = $em->getRepository(Song::class)->find($id);
            if (!$song instanceof Song) continue;

            $item = $em->getRepository(PlaylistItem::class)->findOneBy([
                'playlist' => $playlist,
                'song' => $song,
            ]);
            if (!$item instanceof PlaylistItem) continue;

            $em->remove($item);
            ++$changed;
        }

        $em->flush();
        $this->normalisePositions($playlist, $em);

        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{playlistId}/songs/{itemId}/move', name: 'app_playlist_song_move', requirements: ['playlistId' => '\\d+', 'itemId' => '\\d+'], methods: ['POST'])]
    public function moveSong(int $playlistId, int $itemId, Request $request, EntityManagerInterface $em): Response
    {
        $playlist = $em->getRepository(Playlist::class)->find($playlistId);
        $item = $em->getRepository(PlaylistItem::class)->find($itemId);

        if (!$playlist instanceof Playlist || !$item instanceof PlaylistItem || $item->getPlaylist()->getId() !== $playlist->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_REORDER, $playlist);

        if (!$this->isCsrfTokenValid('playlist_song_move_'.$item->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $direction = (string) $request->request->get('direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            throw $this->createNotFoundException();
        }

        $items = $em->getRepository(PlaylistItem::class)->findBy(
            ['playlist' => $playlist],
            ['position' => 'ASC', 'id' => 'ASC'],
        );

        $index = null;
        foreach ($items as $i => $candidate) {
            if ($candidate->getId() === $item->getId()) {
                $index = $i;
                break;
            }
        }

        if ($index !== null) {
            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (isset($items[$targetIndex])) {
                $target = $items[$targetIndex];
                $current = $item->getPosition();
                $item->setPosition($target->getPosition());
                $target->setPosition($current);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('ids')),
            static fn(int $id): bool => $id > 0,
        )));
    }

    private function validateAjaxCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function normalisePositions(Playlist $playlist, EntityManagerInterface $em): void
    {
        $items = $em->getRepository(PlaylistItem::class)->findBy(
            ['playlist' => $playlist],
            ['position' => 'ASC', 'id' => 'ASC'],
        );

        foreach ($items as $position => $item) {
            $item->setPosition($position);
        }
        $em->flush();
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        return $user;
    }
}
