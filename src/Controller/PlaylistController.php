<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;
use App\Service\ListPagination;
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
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ListPagination $pagination,
    ): Response
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

        $query = $pagination->query($request);
        $letter = $pagination->letter($request);
        $scope = (string) $request->query->get('scope', 'all');
        $songQuery = trim((string) $request->query->get('song', ''));

        $qb = $em->getRepository(Playlist::class)->createQueryBuilder('p')
            ->leftJoin('p.ownerUser', 'owner')
            ->leftJoin(PlaylistInvitation::class, 'piAccess', 'WITH', 'piAccess.playlist = p AND piAccess.invitedUser = :currentUser AND piAccess.status = :accepted')
            ->leftJoin(PlaylistGroup::class, 'pgAccess', 'WITH', 'pgAccess.playlist = p')
            ->leftJoin(GroupMember::class, 'gmAccess', 'WITH', 'gmAccess.group = pgAccess.group AND gmAccess.user = :currentUser')
            ->leftJoin('pgAccess.group', 'groupSearch')
            ->leftJoin(PlaylistItem::class, 'itemSearch', 'WITH', 'itemSearch.playlist = p')
            ->leftJoin('itemSearch.song', 'songSearch')
            ->setParameter('currentUser', $user)
            ->setParameter('accepted', PlaylistInvitationStatus::Accepted)
            ->distinct()
            ->orderBy('LOWER(p.name)', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('(p.public = true OR p.ownerUser = :currentUser OR piAccess.id IS NOT NULL OR gmAccess.id IS NOT NULL)');
        }

        if ($query !== '') {
            $qb->andWhere(
                "(LOWER(p.name) LIKE :q
                  OR LOWER(COALESCE(p.description, '')) LIKE :q
                  OR LOWER(COALESCE(owner.displayName, '')) LIKE :q
                  OR LOWER(COALESCE(groupSearch.name, '')) LIKE :q)"
            )->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if ($songQuery !== '') {
            $qb->andWhere(
                "(LOWER(COALESCE(songSearch.title, '')) LIKE :songQ
                  OR LOWER(COALESCE(songSearch.artist, '')) LIKE :songQ)"
            )->setParameter('songQ', '%'.mb_strtolower($songQuery).'%');
        }

        if ($letter !== null) {
            $qb->andWhere('UPPER(SUBSTRING(p.name, 1, 1)) = :letter')
                ->setParameter('letter', $letter);
        }

        if ($scope === 'mine') {
            $qb->andWhere('p.ownerUser = :currentUser');
        } elseif ($scope === 'group') {
            $qb->andWhere('gmAccess.id IS NOT NULL');
        } elseif ($scope === 'shared') {
            $qb->andWhere('piAccess.id IS NOT NULL');
        } elseif ($scope === 'public') {
            $qb->andWhere('p.public = true');
        } else {
            $scope = 'all';
        }

        $pager = $pagination->paginate($qb, $request, 'p', 'page', 12);
        $visible = $pager['rows'];

        $pendingInvitationCount = $em->getRepository(PlaylistInvitation::class)->count([
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Pending,
        ]);

        $pendingInvitations = $em->getRepository(PlaylistInvitation::class)->createQueryBuilder('pending')
            ->andWhere('pending.invitedUser = :user')
            ->andWhere('pending.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', PlaylistInvitationStatus::Pending)
            ->orderBy('pending.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $acceptedByPlaylist = [];
        $itemsByPlaylist = [];
        $songCountsByPlaylist = [];
        $shareCountsByPlaylist = [];
        $groupsByPlaylist = [];
        $groupCountsByPlaylist = [];

        foreach ($visible as $playlist) {
            $accepted = $em->getRepository(PlaylistInvitation::class)->findOneBy([
                'playlist' => $playlist,
                'invitedUser' => $user,
                'status' => PlaylistInvitationStatus::Accepted,
            ]);
            if ($accepted instanceof PlaylistInvitation) {
                $acceptedByPlaylist[(int) $playlist->getId()] = $accepted;
            }
            $playlistId = (int) $playlist->getId();

            $songCountsByPlaylist[$playlistId] = $em->getRepository(PlaylistItem::class)->count(['playlist' => $playlist]);

            $itemsQb = $em->getRepository(PlaylistItem::class)->createQueryBuilder('i')
                ->join('i.song', 's')
                ->addSelect('s')
                ->andWhere('i.playlist = :playlist')
                ->setParameter('playlist', $playlist)
                ->orderBy('i.position', 'ASC')
                ->addOrderBy('i.id', 'ASC');

            if ($songQuery !== '') {
                $itemsQb->andWhere('(LOWER(s.title) LIKE :sq OR LOWER(s.artist) LIKE :sq)')
                    ->setParameter('sq', '%'.mb_strtolower($songQuery).'%')
                    ->setMaxResults(25);
            } else {
                $itemsQb->setMaxResults(10);
            }

            $itemsByPlaylist[$playlistId] = array_values(array_filter(
                $itemsQb->getQuery()->getResult(),
                fn(PlaylistItem $item): bool => $this->isGranted(AclPrivilege::SONG_VIEW, $item->getSong()),
            ));

            $groupCountsByPlaylist[$playlistId] = $em->getRepository(PlaylistGroup::class)->count(['playlist' => $playlist]);
            $groupsByPlaylist[$playlistId] = array_map(
                static fn(PlaylistGroup $link) => $link->getGroup(),
                $em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist], ['id' => 'ASC'], 8),
            );

            if ($this->isGranted(AclPrivilege::PLAYLIST_INVITE, $playlist)) {
                $shareCountsByPlaylist[$playlistId] = [
                    'pending' => $em->getRepository(PlaylistInvitation::class)->count([
                        'playlist' => $playlist,
                        'status' => PlaylistInvitationStatus::Pending,
                    ]),
                    'accepted' => $em->getRepository(PlaylistInvitation::class)->count([
                        'playlist' => $playlist,
                        'status' => PlaylistInvitationStatus::Accepted,
                    ]),
                ];
            }
        }

        return $this->render('playlists/index.html.twig', [
            'playlists' => $visible,
            'items_by_playlist' => $itemsByPlaylist,
            'song_counts_by_playlist' => $songCountsByPlaylist,
            'share_counts_by_playlist' => $shareCountsByPlaylist,
            'groups_by_playlist' => $groupsByPlaylist,
            'group_counts_by_playlist' => $groupCountsByPlaylist,
            'pending_invitations' => $pendingInvitations,
            'pending_invitation_count' => $pendingInvitationCount,
            'accepted_invitations_by_playlist' => $acceptedByPlaylist,
            'can_publish_playlist' => $this->isGranted('ROLE_EDITOR') || $this->isGranted('ROLE_ADMIN'),
            'pager' => $pager,
            'alphabet' => $pagination->alphabet(),
            'filters' => [
                'q' => $query,
                'letter' => $letter,
                'scope' => $scope,
                'song' => $songQuery,
            ],
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
