<?php
declare(strict_types=1);

namespace App\Domain\Group;

use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_groups')]
class UserGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, GroupMember> */
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: GroupMember::class, orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, PlaylistGroup> */
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: PlaylistGroup::class, orphanRemoval: true)]
    private Collection $playlistLinks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->playlistLinks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description !== null ? trim($description) : null; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, GroupMember> */
    public function getMemberships(): Collection { return $this->memberships; }
    /** @return Collection<int, PlaylistGroup> */
    public function getPlaylistLinks(): Collection { return $this->playlistLinks; }
    /** @return list<Playlist> */
    public function getPlaylists(): array
    {
        return array_values(array_map(
            static fn(PlaylistGroup $link): Playlist => $link->getPlaylist(),
            $this->playlistLinks->toArray(),
        ));
    }
}
