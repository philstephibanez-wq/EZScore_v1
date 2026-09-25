<?php
declare(strict_types=1);

namespace App\Security\Acl;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class GroupVoter extends Voter
{
    private const OBJECT_PRIVILEGES = [
        AclPrivilege::GROUP_VIEW,
        AclPrivilege::GROUP_EDIT,
        AclPrivilege::GROUP_DELETE,
        AclPrivilege::GROUP_MANAGE_MEMBERS,
        AclPrivilege::GROUP_MANAGE_PLAYLISTS,
        AclPrivilege::GROUP_DELEGATE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === AclPrivilege::GROUP_CREATE) return $subject === null;
        return $subject instanceof UserGroup && in_array($attribute, self::OBJECT_PRIVILEGES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) return true;

        if ($attribute === AclPrivilege::GROUP_CREATE) {
            return $this->accessDecisionManager->decide($token, ['ROLE_EDITOR']);
        }

        if (!$subject instanceof UserGroup) return false;

        $membership = $this->em->getRepository(GroupMember::class)->findOneBy([
            'group' => $subject,
            'user' => $user,
        ]);
        if (!$membership instanceof GroupMember) return false;

        return match ($attribute) {
            AclPrivilege::GROUP_VIEW => true,
            AclPrivilege::GROUP_EDIT,
            AclPrivilege::GROUP_MANAGE_MEMBERS,
            AclPrivilege::GROUP_MANAGE_PLAYLISTS
                => in_array($membership->getRole(), ['owner', 'manager'], true),
            AclPrivilege::GROUP_DELETE,
            AclPrivilege::GROUP_DELEGATE
                => $membership->getRole() === 'owner',
            default => false,
        };
    }
}
