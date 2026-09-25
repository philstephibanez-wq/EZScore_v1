<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\Song\SongStatus;
use App\Domain\User\User;
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
        $editableGroups = $this->editableGroups($user, $em);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_playlist', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'playlists.validation.name');
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }

            [$ownerType, $ownerId] = $this->resolveOwner($request, $user, $editableGroups, null);

            $playlist = (new Playlist())
                ->setName($name)
                ->setDescription((string) $request->request->get('description'))
                ->setOwnerType($ownerType)
                ->setOwnerId($ownerId)
                ->setPublic($request->request->getBoolean('public'))
                ->setCreatedBy($user);

            $em->persist($playlist);
            $em->flush();

            $this->addFlash('success', 'playlists.created');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $all = $em->getRepository(Playlist::class)->findBy([], ['name' => 'ASC']);
        $visibleGroupIds = $this->visibleGroupIds($user, $em);

        $visible = array_values(array_filter(
            $all,
            function (Playlist $playlist) use ($user, $visibleGroupIds): bool {
                return $this->isGranted('ROLE_ADMIN')
                    || $playlist->isPublic()
                    || ($playlist->getOwnerType() === 'user' && $playlist->getOwnerId() === $user->getId())
                    || ($playlist->getOwnerType() === 'group' && in_array($playlist->getOwnerId(), $visibleGroupIds, true));
            },
        ));

        $editable = [];
        $itemsByPlaylist = [];
        foreach ($visible as $playlist) {
            $playlistId = (int) $playlist->getId();
            $editable[$playlistId] = $this->canManagePlaylist($playlist, $user, $em);
            $itemsByPlaylist[$playlistId] = $em->getRepository(PlaylistItem::class)->findBy(
                ['playlist' => $playlist],
                ['position' => 'ASC', 'id' => 'ASC'],
            );
        }

        return $this->render('playlists/index.html.twig', [
            'playlists' => $visible,
            'groups' => $editableGroups,
            'editable_playlists' => $editable,
            'items_by_playlist' => $itemsByPlaylist,
            'available_songs' => $this->accessibleSongs($songs, $user),
        ]);
    }

    #[Route('/{id}/update', name: 'app_playlist_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $user = $this->requireUser();

        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_update_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'playlists.validation.name');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $editableGroups = $this->editableGroups($user, $em);
        [$ownerType, $ownerId] = $this->resolveOwner($request, $user, $editableGroups, $playlist);

        $playlist
            ->setName($name)
            ->setDescription((string) $request->request->get('description'))
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
            ->setPublic($request->request->getBoolean('public'));

        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.updated', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/delete', name: 'app_playlist_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $user = $this->requireUser();

        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_delete_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($playlist);
        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.deleted', [], 'management'));
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
        $user = $this->requireUser();
        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_song_add_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $song = $songs->find((int) $request->request->get('song_id'));
        if (!$song instanceof Song || !$this->canAccessSong($song, $user)) {
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
        [$playlist, $item, $user] = $this->requireManagedItem($playlistId, $itemId, $em);

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
        [$playlist, $item] = $this->requireManagedItem($playlistId, $itemId, $em);

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

    private function canManagePlaylist(Playlist $playlist, User $user, EntityManagerInterface $em): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($playlist->getOwnerType() === 'user') {
            return $playlist->getOwnerId() === $user->getId();
        }

        if ($playlist->getOwnerType() !== 'group') {
            return false;
        }

        $group = $em->getRepository(UserGroup::class)->find($playlist->getOwnerId());
        if (!$group instanceof UserGroup) {
            return false;
        }

        $membership = $em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $membership instanceof GroupMember
            && in_array($membership->getRole(), ['owner', 'manager'], true);
    }

    /** @return list<UserGroup> */
    private function editableGroups(User $user, EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);
        }

        $groups = [];
        foreach ($em->getRepository(GroupMember::class)->findBy(['user' => $user]) as $membership) {
            if (in_array($membership->getRole(), ['owner', 'manager'], true)) {
                $groups[] = $membership->getGroup();
            }
        }

        usort($groups, static fn(UserGroup $a, UserGroup $b): int => strcasecmp($a->getName(), $b->getName()));
        return $groups;
    }

    /** @return list<int> */
    private function visibleGroupIds(User $user, EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return array_map(
                static fn(UserGroup $group): int => (int) $group->getId(),
                $em->getRepository(UserGroup::class)->findAll(),
            );
        }

        return array_map(
            static fn(GroupMember $membership): int => (int) $membership->getGroup()->getId(),
            $em->getRepository(GroupMember::class)->findBy(['user' => $user]),
        );
    }

    /** @return list<Song> */
    private function accessibleSongs(SongRepository $songs, User $user): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $songs->findCatalog();
        }

        if ($this->isGranted('ROLE_EDITOR')) {
            return $songs->findForEditor($user);
        }

        return $songs->findPublished();
    }

    private function canAccessSong(Song $song, User $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($song->getStatus() === SongStatus::Published) {
            return true;
        }

        return $this->isGranted('ROLE_EDITOR')
            && $song->getEditor() instanceof User
            && $song->getEditor()->getId() === $user->getId();
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

        $candidate = (int) $request->request->get('group_id');
        foreach ($editableGroups as $group) {
            if ($group->getId() === $candidate) {
                return ['group', $candidate];
            }
        }

        throw $this->createAccessDeniedException();
    }

    /** @return array{0:Playlist,1:PlaylistItem,2:User} */
    private function requireManagedItem(int $playlistId, int $itemId, EntityManagerInterface $em): array
    {
        $user = $this->requireUser();
        $playlist = $em->getRepository(Playlist::class)->find($playlistId);
        $item = $em->getRepository(PlaylistItem::class)->find($itemId);

        if (!$playlist instanceof Playlist
            || !$item instanceof PlaylistItem
            || $item->getPlaylist()->getId() !== $playlist->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        return [$playlist, $item, $user];
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
