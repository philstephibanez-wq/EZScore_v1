<?php

declare(strict_types=1);

namespace App\Domain\Song;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongTimelineEventRepository::class)]
#[ORM\Table(name: 'song_timeline_events')]
#[ORM\Index(name: 'IDX_TIMELINE_SONG_TYPE_START', columns: ['song_id', 'event_type', 'start_ms'])]
class SongTimelineEvent
{
    public const TYPE_BEAT = 'beat';
    public const TYPE_CHORD = 'chord';
    public const TYPE_LYRIC = 'lyric';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(name: 'song_id', nullable: false, onDelete: 'CASCADE')]
    private Song $song;

    #[ORM\Column(name: 'event_type', length: 24)]
    private string $eventType;

    #[ORM\Column(name: 'start_ms')]
    private int $startMs;

    #[ORM\Column(name: 'end_ms', nullable: true)]
    private ?int $endMs = null;

    #[ORM\Column(name: 'measure_index', nullable: true)]
    private ?int $measureIndex = null;

    #[ORM\Column(name: 'beat_index', nullable: true)]
    private ?int $beatIndex = null;

    #[ORM\Column(name: 'subdivision_index', nullable: true)]
    private ?int $subdivisionIndex = null;

    #[ORM\Column(name: 'original_value', length: 64, nullable: true)]
    private ?string $originalValue = null;

    #[ORM\Column(name: 'override_value', length: 64, nullable: true)]
    private ?string $overrideValue = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Song $song, string $eventType, int $startMs)
    {
        if ($startMs < 0) {
            throw new \InvalidArgumentException('Timeline start must be >= 0.');
        }

        $this->song = $song;
        $this->eventType = trim($eventType);
        $this->startMs = $startMs;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getSong(): Song { return $this->song; }
    public function getEventType(): string { return $this->eventType; }
    public function getStartMs(): int { return $this->startMs; }
    public function getEndMs(): ?int { return $this->endMs; }
    public function getMeasureIndex(): ?int { return $this->measureIndex; }
    public function getBeatIndex(): ?int { return $this->beatIndex; }
    public function getSubdivisionIndex(): ?int { return $this->subdivisionIndex; }
    public function getOriginalValue(): ?string { return $this->originalValue; }
    public function getOverrideValue(): ?string { return $this->overrideValue; }
    public function getPayload(): ?array { return $this->payload; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getEffectiveValue(): ?string
    {
        return $this->overrideValue ?? $this->originalValue;
    }

    public function setEndMs(?int $endMs): self
    {
        if ($endMs !== null && $endMs < $this->startMs) {
            throw new \InvalidArgumentException('Timeline end must be >= start.');
        }
        $this->endMs = $endMs;
        return $this->touch();
    }

    public function setPosition(?int $measureIndex, ?int $beatIndex, ?int $subdivisionIndex = null): self
    {
        $this->measureIndex = $measureIndex;
        $this->beatIndex = $beatIndex;
        $this->subdivisionIndex = $subdivisionIndex;
        return $this->touch();
    }

    public function setOriginalValue(?string $value): self
    {
        $this->originalValue = $this->normalise($value);
        return $this->touch();
    }

    public function setOverrideValue(?string $value): self
    {
        $this->overrideValue = $this->normalise($value);
        return $this->touch();
    }

    public function resetOverride(): self
    {
        $this->overrideValue = null;
        return $this->touch();
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this->touch();
    }

    private function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    private function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
