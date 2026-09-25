<?php

declare(strict_types=1);

namespace App\Domain\Playlist;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'playlist_invitations')]
#[ORM\UniqueConstraint(name: 'uniq_playlist_invited_user', columns: ['playlist_id', 'invited_user_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_INVITATION_PLAYLIST', columns: ['playlist_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_INVITATION_USER', columns: ['invited_user_id'])]
#[ORM\Index(name: 'IDX_PLAYLIST_INVITATION_STATUS', columns: ['status'])]
final class PlaylistInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Playlist::class)]
    #[ORM\JoinColumn(name: 'playlist_id', nullable: false, onDelete: 'CASCADE')]
    private Playlist $playlist;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_user_id', nullable: false, onDelete: 'CASCADE')]
    private User $invitedUser;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy;

    #[ORM\Column(length: 16, enumType: PlaylistInvitationStatus::class)]
    private PlaylistInvitationStatus $status = PlaylistInvitationStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct(Playlist $playlist, User $invitedUser, User $invitedBy)
    {
        $this->playlist = $playlist;
        $this->invitedUser = $invitedUser;
        $this->invitedBy = $invitedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPlaylist(): Playlist { return $this->playlist; }
    public function getInvitedUser(): User { return $this->invitedUser; }
    public function getInvitedBy(): ?User { return $this->invitedBy; }
    public function getStatus(): PlaylistInvitationStatus { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }

    public function reopen(User $invitedBy): self
    {
        if ($this->status === PlaylistInvitationStatus::Accepted) {
            throw new \DomainException('An accepted playlist invitation cannot be reopened.');
        }

        $this->invitedBy = $invitedBy;
        $this->status = PlaylistInvitationStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->respondedAt = null;

        return $this;
    }

    public function accept(): self
    {
        $this->assertPending();
        $this->status = PlaylistInvitationStatus::Accepted;
        $this->respondedAt = new \DateTimeImmutable();

        return $this;
    }

    public function decline(): self
    {
        $this->assertPending();
        $this->status = PlaylistInvitationStatus::Declined;
        $this->respondedAt = new \DateTimeImmutable();

        return $this;
    }

    public function cancel(): self
    {
        if (!in_array($this->status, [
            PlaylistInvitationStatus::Pending,
            PlaylistInvitationStatus::Accepted,
        ], true)) {
            throw new \DomainException('Only pending or accepted playlist invitations can be cancelled.');
        }

        $this->status = PlaylistInvitationStatus::Cancelled;
        $this->respondedAt = new \DateTimeImmutable();

        return $this;
    }

    private function assertPending(): void
    {
        if ($this->status !== PlaylistInvitationStatus::Pending) {
            throw new \DomainException('Only pending playlist invitations can be answered.');
        }
    }
}
