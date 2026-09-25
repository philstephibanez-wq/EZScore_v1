<?php
declare(strict_types=1);

namespace App\Domain\Playlist;

use App\Domain\Group\UserGroup;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'playlist_groups')]
#[ORM\UniqueConstraint(name: 'uniq_playlist_group', columns: ['playlist_id', 'group_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_GROUP_PLAYLIST', columns: ['playlist_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_GROUP_GROUP', columns: ['group_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_GROUP_ADDED_BY', columns: ['added_by'])]
final class PlaylistGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Playlist::class, inversedBy: 'groupLinks')]
    #[ORM\JoinColumn(name: 'playlist_id', nullable: false, onDelete: 'CASCADE')]
    private Playlist $playlist;

    #[ORM\ManyToOne(targetEntity: UserGroup::class, inversedBy: 'playlistLinks')]
    #[ORM\JoinColumn(name: 'group_id', nullable: false, onDelete: 'CASCADE')]
    private UserGroup $group;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'added_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $addedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Playlist $playlist, UserGroup $group, ?User $addedBy)
    {
        $this->playlist = $playlist;
        $this->group = $group;
        $this->addedBy = $addedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPlaylist(): Playlist { return $this->playlist; }
    public function getGroup(): UserGroup { return $this->group; }
    public function getAddedBy(): ?User { return $this->addedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
