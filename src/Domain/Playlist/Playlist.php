<?php
declare(strict_types=1);

namespace App\Domain\Playlist;

use App\Domain\Group\UserGroup;
use App\Domain\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'playlists')]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_user_id', nullable: false, onDelete: 'CASCADE')]
    private User $ownerUser;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'CASCADE')]
    private User $createdBy;

    #[ORM\Column(length: 140)]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $public = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PlaylistGroup> */
    #[ORM\OneToMany(mappedBy: 'playlist', targetEntity: PlaylistGroup::class, orphanRemoval: true)]
    private Collection $groupLinks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->groupLinks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description !== null ? trim($description) : null; return $this; }
    public function isPublic(): bool { return $this->public; }
    public function setPublic(bool $public): self { $this->public = $public; return $this; }
    public function getOwnerUser(): User { return $this->ownerUser; }
    public function setOwnerUser(User $user): self { $this->ownerUser = $user; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): self { $this->createdBy = $user; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, PlaylistGroup> */
    public function getGroupLinks(): Collection { return $this->groupLinks; }
    /** @return list<UserGroup> */
    public function getGroups(): array
    {
        return array_values(array_map(
            static fn(PlaylistGroup $link): UserGroup => $link->getGroup(),
            $this->groupLinks->toArray(),
        ));
    }
}
