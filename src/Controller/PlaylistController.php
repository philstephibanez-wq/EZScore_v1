<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/playlists')]
final class PlaylistController extends AbstractController
{
    #[Route('', name: 'app_playlists', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, SongRepository $songs): Response
    {
        $user = $this->requireUser();
        $canUseGroups = $this->isGranted(AclPrivilege::GROUP_CREATE);
        $editableGroups = $canUseGroups ? $this->editableGroups($user, $em) : [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_playlist', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'playlists.validation.name');
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }

            if ($canUseGroups) {
                [$ownerType, $ownerId] = $this->resolveOwner($request, $user, $editableGroups, null);
                $public = $request->request->getBoolean('public');
            } else {
                $ownerType = 'user';
                $ownerId = (int) $user->getId();
                $public = false;
            }

            $playlist = (new Playlist())
                ->setName($name)
                ->setDescription((string) $request->request->get('description'))
                ->setOwnerType($ownerType)
                ->setOwnerId($ownerId)
                ->setPublic($public)
                ->setCreatedBy($user);

            $em->persist($playlist);
            $em->flush();

            $this->addFlash('success', 'playlists.created');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $pendingInvitations = $em->getRepository(PlaylistInvitation::class)->findBy([
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Pending,
        ], ['createdAt' => 'DESC']);

        $acceptedInvitations = $em->getRepository(PlaylistInvitation::class)->findBy([
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Accepted,
        ]);
        $acceptedByPlaylist = [];
        foreach ($acceptedInvitations as $invitation) {
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
        foreach ($visible as $playlist) {
            $playlistId = (int) $playlist->getId();
            $itemsByPlaylist[$playlistId] = array_values(array_filter(
                $em->getRepository(PlaylistItem::class)->findBy(
                    ['playlist' => $playlist],
                    ['position' => 'ASC', 'id' => 'ASC'],
                ),
                fn(PlaylistItem $item): bool => $this->isGranted(AclPrivilege::SONG_VIEW, $item->getSong()),
            ));

            if ($this->isGranted(AclPrivilege::PLAYLIST_INVITE, $playlist)) {
                $invitationsByPlaylist[$playlistId] = $em->getRepository(PlaylistInvitation::class)->findBy(
                    ['playlist' => $playlist],
                    ['createdAt' => 'DESC'],
                );
            }
        }

        $inviteCandidates = array_values(array_filter(
            $em->getRepository(User::class)->findBy(['active' => true], ['displayName' => 'ASC']),
            static fn(User $candidate): bool => $candidate->getId() !== $user->getId(),
        ));

        return $this->render('playlists/index.html.twig', [
            'playlists' => $visible,
            'groups' => $editableGroups,
            'can_use_groups' => $canUseGroups,
            'items_by_playlist' => $itemsByPlaylist,
            'available_songs' => $this->accessibleSongs($songs, $user),
            'pending_invitations' => $pendingInvitations,
            'accepted_invitations_by_playlist' => $acceptedByPlaylist,
            'invitations_by_playlist' => $invitationsByPlaylist,
            'invite_candidates' => $inviteCandidates,
        ]);
    }

    #[Route('/{id}/update', name: 'app_playlist_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_EDIT, $playlist);
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('playlist_update_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'playlists.validation.name');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        if ($this->isGranted(AclPrivilege::GROUP_CREATE)) {
            [$ownerType, $ownerId] = $this->resolveOwner(
                $request,
                $user,
                $this->editableGroups($user, $em),
                $playlist,
            );
            $public = $request->request->getBoolean('public');
        } else {
            if ($playlist->getOwnerType() !== 'user' || $playlist->getOwnerId() !== $user->getId()) {
                throw $this->createAccessDeniedException();
            }
            $ownerType = 'user';
            $ownerId = (int) $user->getId();
            $public = false;
        }

        $playlist
            ->setName($name)
            ->setDescription((string) $request->request->get('description'))
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
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

    #[Route('/{id}/invite', name: 'app_playlist_invite', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function invite(
        Playlist $playlist,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_INVITE, $playlist);
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('playlist_invite_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invited = $em->getRepository(User::class)->find((int) $request->request->get('invited_user_id'));
        if (!$invited instanceof User || !$invited->isActive()) {
            $this->addFlash('error', $translator->trans('playlists.invitation.user_invalid', [], 'management'));
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        if ($invited->getId() === $user->getId()) {
            $this->addFlash('error', $translator->trans('playlists.invitation.self', [], 'management'));
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $repo = $em->getRepository(PlaylistInvitation::class);
        $invitation = $repo->findOneBy(['playlist' => $playlist, 'invitedUser' => $invited]);

        if ($invitation instanceof PlaylistInvitation) {
            if ($invitation->getStatus() === PlaylistInvitationStatus::Pending) {
                $this->addFlash('error', $translator->trans('playlists.invitation.already_pending', [], 'management'));
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }
            if ($invitation->getStatus() === PlaylistInvitationStatus::Accepted) {
                $this->addFlash('error', $translator->trans('playlists.invitation.already_shared', [], 'management'));
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }
            $invitation->reopen($user);
        } else {
            $invitation = new PlaylistInvitation($playlist, $invited, $user);
            $em->persist($invitation);
        }

        $em->flush();
        $this->addFlash('success', $translator->trans(
            'playlists.invitation.sent',
            ['%name%' => $invited->getDisplayName()],
            'management',
        ));

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

    #[Route('/{playlistId}/invitations/{invitationId}/cancel', name: 'app_playlist_invitation_cancel', requirements: ['playlistId' => '\\d+', 'invitationId' => '\\d+'], methods: ['POST'])]
    public function cancelInvitation(
        int $playlistId,
        int $invitationId,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $playlist = $em->getRepository(Playlist::class)->find($playlistId);
        $invitation = $em->getRepository(PlaylistInvitation::class)->find($invitationId);

        if (!$playlist instanceof Playlist
            || !$invitation instanceof PlaylistInvitation
            || $invitation->getPlaylist()->getId() !== $playlist->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_REVOKE_SHARE, $playlist);

        if (!$this->isCsrfTokenValid('playlist_invitation_cancel_'.$invitation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $invitation->cancel();
        } catch (\DomainException) {
            throw $this->createAccessDeniedException();
        }

        $em->flush();
        $this->addFlash('success', $translator->trans('playlists.invitation.cancelled', [], 'management'));

        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/songs', name: 'app_playlist_song_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addSong(
        Playlist $playlist,
        Request $request,
        EntityManagerInterface $em,
        SongRepository $songs,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_ADD_SONG, $playlist);
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('playlist_song_add_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $song = $songs->find((int) $request->request->get('song_id'));
        if (!$song instanceof Song || !$this->isGranted(AclPrivilege::SONG_VIEW, $song)) {
            throw $this->createAccessDeniedException();
        }

        $itemRepo = $em->getRepository(PlaylistItem::class);
        if ($itemRepo->findOneBy(['playlist' => $playlist, 'song' => $song]) instanceof PlaylistItem) {
            $this->addFlash('error', $translator->trans('playlists.song.already_present', [], 'management'));
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $last = $itemRepo->findOneBy(['playlist' => $playlist], ['position' => 'DESC', 'id' => 'DESC']);
        $position = $last instanceof PlaylistItem ? $last->getPosition() + 1 : 0;

        $em->persist(new PlaylistItem($playlist, $song, $user, $position));
        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.song.added', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{playlistId}/songs/{itemId}/delete', name: 'app_playlist_song_delete', requirements: ['playlistId' => '\\d+', 'itemId' => '\\d+'], methods: ['POST'])]
    public function removeSong(
        int $playlistId,
        int $itemId,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        [$playlist, $item] = $this->requireItem($playlistId, $itemId, $em);
        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_REMOVE_SONG, $playlist);

        if (!$this->isCsrfTokenValid('playlist_song_delete_'.$item->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($item);
        $em->flush();
        $this->normalisePositions($playlist, $em);

        $this->addFlash('success', $translator->trans('playlists.song.removed', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{playlistId}/songs/{itemId}/move', name: 'app_playlist_song_move', requirements: ['playlistId' => '\\d+', 'itemId' => '\\d+'], methods: ['POST'])]
    public function moveSong(
        int $playlistId,
        int $itemId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        [$playlist, $item] = $this->requireItem($playlistId, $itemId, $em);
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
                $currentPosition = $item->getPosition();
                $item->setPosition($target->getPosition());
                $target->setPosition($currentPosition);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    /** @return list<UserGroup> */
    private function editableGroups(User $user, EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);
        }

        if (!$this->isGranted('ROLE_EDITOR')) {
            return [];
        }

        $groups = [];
        foreach ($em->getRepository(GroupMember::class)->findBy(['user' => $user]) as $membership) {
            if ($this->isGranted(AclPrivilege::GROUP_EDIT, $membership->getGroup())) {
                $groups[] = $membership->getGroup();
            }
        }

        usort($groups, static fn(UserGroup $a, UserGroup $b): int => strcasecmp($a->getName(), $b->getName()));

        return $groups;
    }

    /** @return list<Song> */
    private function accessibleSongs(SongRepository $songs, User $user): array
    {
        $candidates = $this->isGranted('ROLE_ADMIN')
            ? $songs->findCatalog()
            : ($this->isGranted('ROLE_EDITOR') ? $songs->findForEditor($user) : $songs->findPublished());

        return array_values(array_filter(
            $candidates,
            fn(Song $song): bool => $this->isGranted(AclPrivilege::SONG_VIEW, $song),
        ));
    }

    /**
     * @param list<UserGroup> $editableGroups
     * @return array{0:string,1:int}
     */
    private function resolveOwner(Request $request, User $user, array $editableGroups, ?Playlist $existing): array
    {
        if ((string) $request->request->get('owner_type', 'user') !== 'group') {
            if ($existing instanceof Playlist
                && $this->isGranted('ROLE_ADMIN')
                && $existing->getOwnerType() === 'user') {
                return ['user', $existing->getOwnerId()];
            }

            return ['user', (int) $user->getId()];
        }

        if (!$this->isGranted(AclPrivilege::GROUP_CREATE)) {
            throw $this->createAccessDeniedException();
        }

        $candidate = (int) $request->request->get('group_id');
        foreach ($editableGroups as $group) {
            if ($group->getId() === $candidate) {
                return ['group', $candidate];
            }
        }

        throw $this->createAccessDeniedException();
    }

    /** @return array{0:Playlist,1:PlaylistItem} */
    private function requireItem(int $playlistId, int $itemId, EntityManagerInterface $em): array
    {
        $playlist = $em->getRepository(Playlist::class)->find($playlistId);
        $item = $em->getRepository(PlaylistItem::class)->find($itemId);

        if (!$playlist instanceof Playlist
            || !$item instanceof PlaylistItem
            || $item->getPlaylist()->getId() !== $playlist->getId()) {
            throw $this->createNotFoundException();
        }

        return [$playlist, $item];
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
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
