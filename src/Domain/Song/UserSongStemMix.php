<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSongStemMixRepository::class)]
#[ORM\Table(name: 'user_song_stem_mixes')]
#[ORM\UniqueConstraint(name: 'uniq_user_song_stem_mix', columns: ['user_id', 'song_id'])]
#[ORM\Index(name: 'IDX_USER_SONG_STEM_MIX_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_USER_SONG_STEM_MIX_SONG', columns: ['song_id'])]
final class UserSongStemMix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(name: 'song_id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    private array $settings = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, Song $song)
    {
        $this->user = $user;
        $this->song = $song;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSong(): Song { return $this->song; }

    /** @return array<string,mixed> */
    public function getSettings(): array { return $this->settings; }

    /** @param array<string,mixed> $settings */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
