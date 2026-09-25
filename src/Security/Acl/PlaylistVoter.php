<?php

declare(strict_types=1);

namespace App\Security\Acl;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Native Symfony implementation of the OPUS "resource + privilege + conditions" model
 * for Playlist resources.
 */
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
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Playlist && in_array($attribute, self::SUPPORTED, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Playlist) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return match ($attribute) {
            AclPrivilege::PLAYLIST_VIEW => $this->canView($subject, $user),
            AclPrivilege::PLAYLIST_EDIT,
            AclPrivilege::PLAYLIST_DELETE,
            AclPrivilege::PLAYLIST_ADD_SONG,
            AclPrivilege::PLAYLIST_REMOVE_SONG,
            AclPrivilege::PLAYLIST_REORDER => $this->canManage($subject, $user, $token),
            AclPrivilege::PLAYLIST_INVITE,
            AclPrivilege::PLAYLIST_REVOKE_SHARE => $this->isPersonalOwner($subject, $user),
            default => false,
        };
    }

    private function canView(Playlist $playlist, User $user): bool
    {
        if ($playlist->isPublic() || $this->isPersonalOwner($playlist, $user)) {
            return true;
        }

        if ($playlist->getOwnerType() === 'group') {
            return $this->isGroupMember($playlist->getOwnerId(), $user);
        }

        return $this->hasAcceptedInvitation($playlist, $user);
    }

    private function canManage(Playlist $playlist, User $user, TokenInterface $token): bool
    {
        if ($this->isPersonalOwner($playlist, $user)) {
            return true;
        }

        if ($playlist->getOwnerType() !== 'group'
            || !$this->accessDecisionManager->decide($token, ['ROLE_EDITOR'])) {
            return false;
        }

        $group = $this->em->getRepository(UserGroup::class)->find($playlist->getOwnerId());
        if (!$group instanceof UserGroup) {
            return false;
        }

        $membership = $this->em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $membership instanceof GroupMember
            && in_array($membership->getRole(), ['owner', 'manager'], true);
    }

    private function isPersonalOwner(Playlist $playlist, User $user): bool
    {
        return $playlist->getOwnerType() === 'user'
            && $playlist->getOwnerId() === $user->getId();
    }

    private function isGroupMember(int $groupId, User $user): bool
    {
        $group = $this->em->getRepository(UserGroup::class)->find($groupId);
        if (!$group instanceof UserGroup) {
            return false;
        }

        return $this->em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]) instanceof GroupMember;
    }

    private function hasAcceptedInvitation(Playlist $playlist, User $user): bool
    {
        return $this->em->getRepository(PlaylistInvitation::class)->findOneBy([
            'playlist' => $playlist,
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Accepted,
        ]) instanceof PlaylistInvitation;
    }
}
