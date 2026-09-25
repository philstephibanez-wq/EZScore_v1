<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\Song\SongRepository;
use App\Domain\Song\SongStatus;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Security\Acl\AclPrivilege;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/picker')]
final class PickerController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('/users', name: 'app_picker_users', methods: ['GET'])]
    public function users(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): JsonResponse {
        $current = $this->requireUser();
        $groupId = $request->query->getInt('group_id');
        $playlistId = $request->query->getInt('playlist_id');
        $selectedIds = [];

        if ($groupId > 0) {
            $group = $em->getRepository(UserGroup::class)->find($groupId);
            if (!$group instanceof UserGroup) throw $this->createNotFoundException();
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);

            foreach ($em->getRepository(GroupMember::class)->findBy(['group' => $group]) as $membership) {
                $selectedIds[] = (int) $membership->getUser()->getId();
            }
        } elseif ($playlistId > 0) {
            $playlist = $em->getRepository(Playlist::class)->find($playlistId);
            if (!$playlist instanceof Playlist) throw $this->createNotFoundException();
            $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_INVITE, $playlist);

            foreach ($em->getRepository(PlaylistInvitation::class)->findBy(['playlist' => $playlist]) as $invitation) {
                if (in_array($invitation->getStatus(), [
                    PlaylistInvitationStatus::Pending,
                    PlaylistInvitationStatus::Accepted,
                ], true)) {
                    $selectedIds[] = (int) $invitation->getInvitedUser()->getId();
                }
            }
        } else {
            throw $this->createNotFoundException();
        }

        $qb = $users->createQueryBuilder('u');
        $this->applyMembership($qb, 'u.id', $selectedIds, (string) $request->query->get('membership', 'out'));

        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') {
            $qb->andWhere('LOWER(u.displayName) LIKE :q')->setParameter('q', '%'.$query.'%');
        }

        $role = (string) $request->query->get('role', '');
        if (in_array($role, ['ROLE_READER', 'ROLE_EDITOR', 'ROLE_ADMIN'], true)) {
            $qb->andWhere('u.roles LIKE :role')->setParameter('role', '%'.$role.'%');
        }

        $state = (string) $request->query->get('state', 'all');
        if ($state === 'active') $qb->andWhere('u.active = true');
        elseif ($state === 'inactive') $qb->andWhere('u.active = false');

        if ($playlistId > 0) {
            $qb->andWhere('u.id != :currentUser')->setParameter('currentUser', $current->getId());
        }

        $qb->orderBy('LOWER(u.displayName)', 'ASC');
        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(User $user): array => [
                'id' => (int) $user->getId(),
                'label' => $user->getDisplayName(),
                'meta' => [
                    $translator->trans('role.'.$user->getPrimaryRole()),
                    '#'.$user->getId(),
                    $user->isActive()
                        ? $translator->trans('picker.state.active', [], 'picker')
                        : $translator->trans('picker.state.inactive', [], 'picker'),
                ],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [
                [
                    'name' => 'role',
                    'label' => $translator->trans('picker.filter.role', [], 'picker'),
                    'options' => [
                        ['value' => '', 'label' => $translator->trans('picker.filter.all', [], 'picker')],
                        ['value' => 'ROLE_READER', 'label' => $translator->trans('role.ROLE_READER')],
                        ['value' => 'ROLE_EDITOR', 'label' => $translator->trans('role.ROLE_EDITOR')],
                        ['value' => 'ROLE_ADMIN', 'label' => $translator->trans('role.ROLE_ADMIN')],
                    ],
                ],
                [
                    'name' => 'state',
                    'label' => $translator->trans('picker.filter.state', [], 'picker'),
                    'options' => [
                        ['value' => 'all', 'label' => $translator->trans('picker.filter.all', [], 'picker')],
                        ['value' => 'active', 'label' => $translator->trans('picker.state.active', [], 'picker')],
                        ['value' => 'inactive', 'label' => $translator->trans('picker.state.inactive', [], 'picker')],
                    ],
                ],
            ],
        ]);
    }

    #[Route('/songs', name: 'app_picker_songs', methods: ['GET'])]
    public function songs(
        Request $request,
        SongRepository $songs,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): JsonResponse {
        $user = $this->requireUser();
        $playlist = $em->getRepository(Playlist::class)->find($request->query->getInt('playlist_id'));
        if (!$playlist instanceof Playlist) throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_EDIT, $playlist);

        $selectedIds = [];
        foreach ($em->getRepository(PlaylistItem::class)->findBy(['playlist' => $playlist]) as $item) {
            $selectedIds[] = (int) $item->getSong()->getId();
        }

        $qb = $songs->createQueryBuilder('s')->leftJoin('s.editor', 'e')->addSelect('e');
        $this->applyMembership($qb, 's.id', $selectedIds, (string) $request->query->get('membership', 'out'));

        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($this->isGranted('ROLE_EDITOR')) {
                $qb->andWhere('(s.status = :published OR s.editor = :editor)')
                    ->setParameter('published', SongStatus::Published->value)
                    ->setParameter('editor', $user);
            } else {
                $qb->andWhere('s.status = :published')->setParameter('published', SongStatus::Published->value);
            }
        }

        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') {
            $qb->andWhere('(LOWER(s.title) LIKE :q OR LOWER(s.artist) LIKE :q)')
                ->setParameter('q', '%'.$query.'%');
        }

        $status = (string) $request->query->get('status', '');
        $allowed = array_map(static fn(SongStatus $case): string => $case->value, SongStatus::cases());
        if (in_array($status, $allowed, true)) {
            $qb->andWhere('s.status = :statusFilter')->setParameter('statusFilter', $status);
        }

        $qb->orderBy('LOWER(s.artist)', 'ASC')->addOrderBy('LOWER(s.title)', 'ASC');
        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(Song $song): array => [
                'id' => (int) $song->getId(),
                'label' => $song->getArtist().' — '.$song->getTitle(),
                'meta' => [
                    $translator->trans('song.status.'.$song->getStatus()->value),
                    '#'.$song->getId(),
                ],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [[
                'name' => 'status',
                'label' => $translator->trans('picker.filter.status', [], 'picker'),
                'options' => array_merge(
                    [['value' => '', 'label' => $translator->trans('picker.filter.all', [], 'picker')]],
                    array_map(static fn(SongStatus $case): array => [
                        'value' => $case->value,
                        'label' => $translator->trans('song.status.'.$case->value),
                    ], SongStatus::cases()),
                ),
            ]],
        ]);
    }

    #[Route('/playlists', name: 'app_picker_playlists', methods: ['GET'])]
    public function playlists(
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): JsonResponse {
        $user = $this->requireUser();
        $group = $em->getRepository(UserGroup::class)->find($request->query->getInt('group_id'));
        if (!$group instanceof UserGroup) throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_PLAYLISTS, $group);

        $selectedIds = [];
        foreach ($em->getRepository(PlaylistGroup::class)->findBy(['group' => $group]) as $link) {
            $selectedIds[] = (int) $link->getPlaylist()->getId();
        }

        $membership = (string) $request->query->get('membership', 'out');
        $qb = $em->getRepository(Playlist::class)->createQueryBuilder('p')
            ->leftJoin('p.ownerUser', 'o')
            ->addSelect('o');

        $this->applyMembership($qb, 'p.id', $selectedIds, $membership);

        if (!$this->isGranted('ROLE_ADMIN') && $membership !== 'in') {
            $qb->andWhere('p.ownerUser = :owner')->setParameter('owner', $user);
        }

        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') {
            $qb->andWhere("(LOWER(p.name) LIKE :q OR LOWER(COALESCE(p.description, '')) LIKE :q)")
                ->setParameter('q', '%'.$query.'%');
        }

        $visibility = (string) $request->query->get('visibility', '');
        if ($visibility === 'public') $qb->andWhere('p.public = true');
        elseif ($visibility === 'private') $qb->andWhere('p.public = false');

        $qb->orderBy('LOWER(p.name)', 'ASC');
        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(Playlist $playlist): array => [
                'id' => (int) $playlist->getId(),
                'label' => $playlist->getName(),
                'meta' => [
                    $playlist->getOwnerUser()->getDisplayName(),
                    $playlist->isPublic()
                        ? $translator->trans('picker.visibility.public', [], 'picker')
                        : $translator->trans('picker.visibility.private', [], 'picker'),
                    '#'.$playlist->getId(),
                ],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [[
                'name' => 'visibility',
                'label' => $translator->trans('picker.filter.visibility', [], 'picker'),
                'options' => [
                    ['value' => '', 'label' => $translator->trans('picker.filter.all', [], 'picker')],
                    ['value' => 'private', 'label' => $translator->trans('picker.visibility.private', [], 'picker')],
                    ['value' => 'public', 'label' => $translator->trans('picker.visibility.public', [], 'picker')],
                ],
            ]],
        ]);
    }

    private function applyMembership(QueryBuilder $qb, string $field, array $selectedIds, string $membership): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));

        if ($membership === 'in') {
            if ($selectedIds === []) {
                $qb->andWhere('1 = 0');
                return;
            }
            $qb->andWhere($field.' IN (:selectedIds)')->setParameter('selectedIds', $selectedIds);
            return;
        }

        if ($membership === 'out' && $selectedIds !== []) {
            $qb->andWhere($field.' NOT IN (:selectedIds)')->setParameter('selectedIds', $selectedIds);
        }
    }

    /** @return array{0:list<object>,1:int,2:int,3:int} */
    private function paginate(QueryBuilder $qb, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $countQb = clone $qb;
        $alias = $qb->getRootAliases()[0];

        $total = (int) $countQb
            ->select('COUNT(DISTINCT '.$alias.'.id)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $rows = $qb
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return [$rows, $page, $pages, $total];
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        return $user;
    }
}
