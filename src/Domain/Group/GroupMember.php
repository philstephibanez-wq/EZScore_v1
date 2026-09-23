<?php

declare(strict_types=1);

namespace App\Domain\Group;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'group_members')]
#[ORM\UniqueConstraint(name: 'uniq_group_member', columns: ['group_id', 'user_id'])]
class GroupMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserGroup::class)]
    #[ORM\JoinColumn(name: 'group_id', nullable: false, onDelete: 'CASCADE')]
    private UserGroup $group;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $role = 'member';

    public function getId(): ?int { return $this->id; }
    public function getGroup(): UserGroup { return $this->group; }
    public function setGroup(UserGroup $group): self { $this->group = $group; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = in_array($role, ['owner','manager','member'], true) ? $role : 'member'; return $this; }
}
