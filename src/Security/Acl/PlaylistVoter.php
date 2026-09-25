<?php
declare(strict_types=1);

namespace App\Security\Acl;

use App\Domain\Group\GroupMember;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PlaylistVoter extends Voter
{
    private const SUPPORTED = [
        AclPrivilege::PLAYLIST_VIEW,
        AclPrivilege::PLAYLIST_EDIT,
        AclPrivilege::PLAYLIST_DELETE,
        AclPrivilege::PLAYLIST_ADD_SONG,
        AclPrivilege::PLAYLIST_REMOVE_SONG,
        AclPrivilege::PLAYLIST_REORDER,
        AclPrivilege::PLAYLIST_INVITE,
        AclPrivilege::PLAYLIST_REVOKE_SHARE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Playlist && in_array($attribute, self::SUPPORTED, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Playlist) return false;

        $user = $token->getUser();
        if (!$user instanceof User) return false;

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return match ($attribute) {
            AclPrivilege::PLAYLIST_VIEW => $this->canView($subject, $user),
            AclPrivilege::PLAYLIST_EDIT,
            AclPrivilege::PLAYLIST_DELETE,
            AclPrivilege::PLAYLIST_ADD_SONG,
            AclPrivilege::PLAYLIST_REMOVE_SONG,
            AclPrivilege::PLAYLIST_REORDER,
            AclPrivilege::PLAYLIST_INVITE,
            AclPrivilege::PLAYLIST_REVOKE_SHARE
                => $subject->getOwnerUser()->getId() === $user->getId(),
            default => false,
        };
    }

    private function canView(Playlist $playlist, User $user): bool
    {
        if ($playlist->isPublic() || $playlist->getOwnerUser()->getId() === $user->getId()) {
            return true;
        }

        if ($this->em->getRepository(PlaylistInvitation::class)->findOneBy([
            'playlist' => $playlist,
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Accepted,
        ]) instanceof PlaylistInvitation) {
            return true;
        }

        foreach ($this->em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist]) as $link) {
            if ($this->em->getRepository(GroupMember::class)->findOneBy([
                'group' => $link->getGroup(),
                'user' => $user,
            ]) instanceof GroupMember) {
                return true;
            }
        }

        return false;
    }
}
