<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'song_publication_notifications')]
#[ORM\UniqueConstraint(name: 'uniq_song_publication_notification', columns: ['song_id', 'user_id'])]
#[ORM\Index(name: 'IDX_SONG_PUBLICATION_NOTIFICATION_SONG', columns: ['song_id'])]
#[ORM\Index(name: 'IDX_SONG_PUBLICATION_NOTIFICATION_USER', columns: ['user_id'])]
final class SongPublicationNotification
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
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lastError = null;

    public function __construct(Song $song, User $user)
    {
        $this->song = $song;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSong(): Song { return $this->song; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getFailedAt(): ?\DateTimeImmutable { return $this->failedAt; }
    public function getLastError(): ?string { return $this->lastError; }
    public function wasSent(): bool { return $this->sentAt !== null; }

    public function markSent(): self
    {
        $this->sentAt = new \DateTimeImmutable();
        $this->failedAt = null;
        $this->lastError = null;
        return $this;
    }

    public function markFailed(string $message): self
    {
        $this->failedAt = new \DateTimeImmutable();
        $this->lastError = mb_substr(trim($message), 0, 500);
        return $this;
    }
}
