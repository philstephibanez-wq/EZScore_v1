<?php

declare(strict_types=1);

namespace App\Domain\Playlist;

use App\Domain\Song\Song;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'playlist_items')]
#[ORM\UniqueConstraint(name: 'uniq_playlist_item_song', columns: ['playlist_id', 'song_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_ITEM_PLAYLIST', columns: ['playlist_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_ITEM_SONG', columns: ['song_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_ITEM_ADDED_BY', columns: ['added_by'])]
#[ORM\Index(name: 'IDX_PLAYLIST_ITEM_POSITION', columns: ['playlist_id', 'position'])]
final class PlaylistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Playlist::class)]
    #[ORM\JoinColumn(name: 'playlist_id', nullable: false, onDelete: 'CASCADE')]
    private Playlist $playlist;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(name: 'song_id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'added_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $addedBy;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Playlist $playlist, Song $song, ?User $addedBy, int $position = 0)
    {
        $this->playlist = $playlist;
        $this->song = $song;
        $this->addedBy = $addedBy;
        $this->setPosition($position);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPlaylist(): Playlist { return $this->playlist; }
    public function getSong(): Song { return $this->song; }
    public function getAddedBy(): ?User { return $this->addedBy; }
    public function getPosition(): int { return $this->position; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setPosition(int $position): self
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Playlist item position cannot be negative.');
        }

        $this->position = $position;
        return $this;
    }
}
