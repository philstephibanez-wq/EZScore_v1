<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongRatingRepository::class)]
#[ORM\Table(name: 'song_ratings')]
#[ORM\UniqueConstraint(name: 'uniq_song_rating_user', columns: ['song_id', 'user_id'])]
#[ORM\Index(name: 'IDX_SONG_RATING_SONG', columns: ['song_id'])]
#[ORM\Index(name: 'IDX_SONG_RATING_USER', columns: ['user_id'])]
class SongRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(name: 'song_id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $rating;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Song $song, User $user, int $rating)
    {
        $this->song = $song;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->setRating($rating);
    }

    public function getId(): ?int { return $this->id; }
    public function getSong(): Song { return $this->song; }
    public function getUser(): User { return $this->user; }
    public function getRating(): int { return $this->rating; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setRating(int $rating): self
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Song rating must be between 1 and 5.');
        }

        $this->rating = $rating;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
