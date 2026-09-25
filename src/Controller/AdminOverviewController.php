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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminOverviewController extends AbstractController
{
    #[Route('', name: 'admin_overview', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findBy([], ['displayName' => 'ASC']);
        $groups = $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);
        $playlists = $em->getRepository(Playlist::class)->findBy([], ['name' => 'ASC']);

        $membersByGroup = [];
        foreach ($em->getRepository(GroupMember::class)->findAll() as $membership) {
            $membersByGroup[(int) $membership->getGroup()->getId()][] = $membership;
        }

        $linksByGroup = [];
        $linksByPlaylist = [];
        foreach ($em->getRepository(PlaylistGroup::class)->findAll() as $link) {
            $linksByGroup[(int) $link->getGroup()->getId()][] = $link;
            $linksByPlaylist[(int) $link->getPlaylist()->getId()][] = $link;
        }

        $itemsByPlaylist = [];
        foreach ($em->getRepository(PlaylistItem::class)->findAll() as $item) {
            $itemsByPlaylist[(int) $item->getPlaylist()->getId()][] = $item;
        }

        $invitationsByPlaylist = [];
        foreach ($em->getRepository(PlaylistInvitation::class)->findAll() as $invitation) {
            $invitationsByPlaylist[(int) $invitation->getPlaylist()->getId()][] = $invitation;
        }

        $groupRows = [];
        foreach ($groups as $group) {
            $memberships = $membersByGroup[(int) $group->getId()] ?? [];
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
                'playlist_count' => count($linksByGroup[(int) $group->getId()] ?? []),
            ];
        }

        $playlistRows = [];
        foreach ($playlists as $playlist) {
            $invitationStats = [
                PlaylistInvitationStatus::Pending->value => 0,
                PlaylistInvitationStatus::Accepted->value => 0,
                PlaylistInvitationStatus::Declined->value => 0,
                PlaylistInvitationStatus::Cancelled->value => 0,
            ];

            foreach ($invitationsByPlaylist[(int) $playlist->getId()] ?? [] as $invitation) {
                ++$invitationStats[$invitation->getStatus()->value];
            }

            $groupNames = array_map(
                static fn(PlaylistGroup $link): string => $link->getGroup()->getName(),
                $linksByPlaylist[(int) $playlist->getId()] ?? [],
            );

            $playlistRows[] = [
                'playlist' => $playlist,
                'owner_label' => $playlist->getOwnerUser()->getDisplayName(),
                'creator' => $playlist->getCreatedBy(),
                'group_names' => $groupNames,
                'song_count' => count($itemsByPlaylist[(int) $playlist->getId()] ?? []),
                'invitation_stats' => $invitationStats,
            ];
        }

        $roleCounts = ['ROLE_READER' => 0, 'ROLE_EDITOR' => 0, 'ROLE_ADMIN' => 0];
        $activeUsers = 0;

        foreach ($users as $user) {
            if ($user->isActive()) ++$activeUsers;
            if (isset($roleCounts[$user->getPrimaryRole()])) {
                ++$roleCounts[$user->getPrimaryRole()];
            }
        }

        return $this->render('admin/overview.html.twig', [
            'stats' => [
                'users' => count($users),
                'active_users' => $activeUsers,
                'readers' => $roleCounts['ROLE_READER'],
                'editors' => $roleCounts['ROLE_EDITOR'],
                'admins' => $roleCounts['ROLE_ADMIN'],
                'groups' => count($groups),
                'playlists' => count($playlists),
            ],
            'group_rows' => $groupRows,
            'playlist_rows' => $playlistRows,
        ]);
    }
}
