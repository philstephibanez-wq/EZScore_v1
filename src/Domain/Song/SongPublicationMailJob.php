<?php

declare(strict_types=1);

namespace App\Domain\Song;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'song_publication_mail_jobs')]
#[ORM\UniqueConstraint(name: 'uniq_song_publication_mail_job_song', columns: ['song_id'])]
#[ORM\Index(name: 'IDX_SONG_PUBLICATION_MAIL_JOB_COMPLETED', columns: ['completed_at'])]
#[ORM\Index(name: 'IDX_SONG_PUBLICATION_MAIL_JOB_CLAIM', columns: ['claimed_at'])]
final class SongPublicationMailJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(name: 'song_id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $claimToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextAttemptAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lastError = null;

    public function __construct(Song $song)
    {
        $this->song = $song;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSong(): Song { return $this->song; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getClaimedAt(): ?\DateTimeImmutable { return $this->claimedAt; }
    public function getClaimToken(): ?string { return $this->claimToken; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getNextAttemptAt(): ?\DateTimeImmutable { return $this->nextAttemptAt; }
    public function getAttempts(): int { return $this->attempts; }
    public function getLastError(): ?string { return $this->lastError; }
    public function isCompleted(): bool { return $this->completedAt !== null; }

    public function claim(string $token, \DateTimeImmutable $at): self
    {
        $this->claimToken = $token;
        $this->claimedAt = $at;
        ++$this->attempts;
        return $this;
    }

    public function complete(): self
    {
        $this->completedAt = new \DateTimeImmutable();
        $this->claimToken = null;
        $this->claimedAt = null;
        $this->lastError = null;
        $this->nextAttemptAt = null;
        return $this;
    }

    public function releaseWithError(string $message): self
    {
        $this->claimToken = null;
        $this->claimedAt = null;
        $this->lastError = mb_substr(trim($message), 0, 500);

        $delayMinutes = min(60, max(1, 2 ** min($this->attempts, 5)));
        $this->nextAttemptAt = new \DateTimeImmutable(sprintf('+%d minutes', $delayMinutes));

        return $this;
    }
}
