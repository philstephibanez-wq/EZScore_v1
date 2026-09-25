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
use App\Domain\User\User;
use App\Service\ListPagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminOverviewController extends AbstractController
{
    #[Route('', name: 'admin_overview', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ListPagination $pagination,
    ): Response {
        $userRepo = $em->getRepository(User::class);
        $groupRepo = $em->getRepository(UserGroup::class);
        $playlistRepo = $em->getRepository(Playlist::class);

        $stats = [
            'users' => $userRepo->count([]),
            'active_users' => $userRepo->count(['active' => true]),
            'readers' => $this->countRole($em, 'ROLE_READER'),
            'editors' => $this->countRole($em, 'ROLE_EDITOR'),
            'admins' => $this->countRole($em, 'ROLE_ADMIN'),
            'groups' => $groupRepo->count([]),
            'playlists' => $playlistRepo->count([]),
        ];

        $gq = $pagination->query($request, 'gq');
        $gletter = $pagination->letter($request, 'gletter');
        $grole = (string) $request->query->get('grole', '');

        $groupQb = $groupRepo->createQueryBuilder('g')
            ->leftJoin(GroupMember::class, 'gm', 'WITH', 'gm.group = g')
            ->leftJoin('gm.user', 'gu')
            ->distinct()
            ->orderBy('LOWER(g.name)', 'ASC');

        if ($gq !== '') {
            $groupQb->andWhere(
                "(LOWER(g.name) LIKE :gq
                  OR LOWER(COALESCE(g.description, '')) LIKE :gq
                  OR LOWER(COALESCE(gu.displayName, '')) LIKE :gq
                  OR LOWER(COALESCE(gu.email, '')) LIKE :gq)"
            )->setParameter('gq', '%'.mb_strtolower($gq).'%');
        }

        if ($gletter !== null) {
            $groupQb->andWhere('UPPER(SUBSTRING(g.name, 1, 1)) = :gletter')
                ->setParameter('gletter', $gletter);
        }

        if (in_array($grole, ['owner', 'manager', 'member'], true)) {
            $groupQb->andWhere('gm.role = :grole')->setParameter('grole', $grole);
        }

        $groupPager = $pagination->paginate($groupQb, $request, 'g', 'gpage', 25);
        $groupRows = [];

        foreach ($groupPager['rows'] as $group) {
            $memberships = $em->getRepository(GroupMember::class)->findBy(
                ['group' => $group],
                ['role' => 'ASC', 'id' => 'ASC'],
            );
            $owners = [];
            $managers = [];
            $members = [];

            foreach ($memberships as $membership) {
                $entry = [
                    'name' => $membership->getUser()->getDisplayName(),
                    'email' => $membership->getUser()->getEmail(),
                ];

                match ($membership->getRole()) {
                    'owner' => $owners[] = $entry,
                    'manager' => $managers[] = $entry,
                    default => $members[] = $entry,
                };
            }

            $groupRows[] = [
                'group' => $group,
                'owners' => $owners,
                'managers' => $managers,
                'members' => $members,
                'member_count' => count($memberships),
                'playlist_count' => $em->getRepository(PlaylistGroup::class)->count(['group' => $group]),
            ];
        }

        $pq = $pagination->query($request, 'pq');
        $pletter = $pagination->letter($request, 'pletter');
        $pvisibility = (string) $request->query->get('pvisibility', '');

        $playlistQb = $playlistRepo->createQueryBuilder('p')
            ->leftJoin('p.ownerUser', 'po')
            ->leftJoin(PlaylistGroup::class, 'pg', 'WITH', 'pg.playlist = p')
            ->leftJoin('pg.group', 'pgroup')
            ->addSelect('po')
            ->orderBy('LOWER(p.name)', 'ASC');

        if ($pq !== '') {
            $playlistQb->andWhere(
                "(LOWER(p.name) LIKE :pq
                  OR LOWER(COALESCE(p.description, '')) LIKE :pq
                  OR LOWER(COALESCE(po.displayName, '')) LIKE :pq
                  OR LOWER(COALESCE(pgroup.name, '')) LIKE :pq)"
            )->setParameter('pq', '%'.mb_strtolower($pq).'%');
        }

        if ($pletter !== null) {
            $playlistQb->andWhere('UPPER(SUBSTRING(p.name, 1, 1)) = :pletter')
                ->setParameter('pletter', $pletter);
        }

        if ($pvisibility === 'public') $playlistQb->andWhere('p.public = true');
        elseif ($pvisibility === 'private') $playlistQb->andWhere('p.public = false');

        $playlistPager = $pagination->paginate($playlistQb, $request, 'p', 'ppage', 25);
        $playlistRows = [];

        foreach ($playlistPager['rows'] as $playlist) {
            $invitationStats = [
                PlaylistInvitationStatus::Pending->value => 0,
                PlaylistInvitationStatus::Accepted->value => 0,
                PlaylistInvitationStatus::Declined->value => 0,
                PlaylistInvitationStatus::Cancelled->value => 0,
            ];

            foreach ($em->getRepository(PlaylistInvitation::class)->findBy(['playlist' => $playlist]) as $invitation) {
                ++$invitationStats[$invitation->getStatus()->value];
            }

            $groupNames = array_map(
                static fn(PlaylistGroup $link): string => $link->getGroup()->getName(),
                $em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist]),
            );

            $playlistRows[] = [
                'playlist' => $playlist,
                'owner_label' => $playlist->getOwnerUser()->getDisplayName(),
                'creator' => $playlist->getCreatedBy(),
                'group_names' => $groupNames,
                'song_count' => $em->getRepository(PlaylistItem::class)->count(['playlist' => $playlist]),
                'invitation_stats' => $invitationStats,
            ];
        }

        return $this->render('admin/overview.html.twig', [
            'stats' => $stats,
            'group_rows' => $groupRows,
            'group_pager' => $groupPager,
            'playlist_rows' => $playlistRows,
            'playlist_pager' => $playlistPager,
            'alphabet' => $pagination->alphabet(),
            'group_filters' => ['q' => $gq, 'letter' => $gletter, 'role' => $grole],
            'playlist_filters' => ['q' => $pq, 'letter' => $pletter, 'visibility' => $pvisibility],
        ]);
    }

    private function countRole(EntityManagerInterface $em, string $role): int
    {
        return (int) $em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%'.$role.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
