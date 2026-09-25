<?php
declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_participants')]
#[ORM\UniqueConstraint(name: 'uniq_event_participant', columns: ['event_id', 'user_id'])]
#[ORM\Index(name: 'IDX_EVENT_PARTICIPANT_EVENT', columns: ['event_id'])]
#[ORM\Index(name: 'IDX_EVENT_PARTICIPANT_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_EVENT_PARTICIPANT_STATUS', columns: ['status'])]
final class EventParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'event_id', nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: EventParticipantStatus::class)]
    private EventParticipantStatus $status = EventParticipantStatus::Invited;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailNotifiedAt = null;

    public function __construct(Event $event, User $user, EventParticipantStatus $status = EventParticipantStatus::Invited)
    {
        $this->event = $event;
        $this->user = $user;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): Event { return $this->event; }
    public function getUser(): User { return $this->user; }
    public function getStatus(): EventParticipantStatus { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }
    public function getEmailNotifiedAt(): ?\DateTimeImmutable { return $this->emailNotifiedAt; }

    public function respond(EventParticipantStatus $status): self
    {
        if (!in_array($status, [EventParticipantStatus::Accepted, EventParticipantStatus::Declined, EventParticipantStatus::Maybe], true)) {
            throw new \InvalidArgumentException('Invalid RSVP status.');
        }
        $this->status = $status;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markEmailNotified(): self
    {
        $this->emailNotifiedAt = new \DateTimeImmutable();
        return $this;
    }
}
