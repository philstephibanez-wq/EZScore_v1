<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\Table(name: 'songs')]
class Song
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 180)]
    private string $artist = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'editor_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $editor = null;

    #[ORM\Column(length: 8)]
    private string $timeSignature = '4/4';

    #[ORM\Column]
    private int $capo = 0;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $strummingPrimary = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $strummingAlternate = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this->touch(); }
    public function getArtist(): string { return $this->artist; }
    public function setArtist(string $artist): self { $this->artist = trim($artist); return $this->touch(); }
    public function getEditor(): ?User { return $this->editor; }
    public function setEditor(?User $editor): self { $this->editor = $editor; return $this->touch(); }
    public function getTimeSignature(): string { return $this->timeSignature; }

    public function setTimeSignature(string $timeSignature): self
    {
        $allowed = ['2/4', '3/4', '4/4', '6/8'];
        if (!in_array($timeSignature, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported time signature "%s".', $timeSignature));
        }
        $this->timeSignature = $timeSignature;
        return $this->touch();
    }

    public function getCapo(): int { return $this->capo; }

    public function setCapo(int $capo): self
    {
        if ($capo < 0 || $capo > 11) {
            throw new \InvalidArgumentException('Capo must be between 0 and 11.');
        }
        $this->capo = $capo;
        return $this->touch();
    }

    public function getStrummingPrimary(): ?string { return $this->strummingPrimary; }
    public function setStrummingPrimary(?string $value): self { $this->strummingPrimary = $this->normaliseNullable($value); return $this->touch(); }
    public function getStrummingAlternate(): ?string { return $this->strummingAlternate; }
    public function setStrummingAlternate(?string $value): self { $this->strummingAlternate = $this->normaliseNullable($value); return $this->touch(); }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $this->normaliseNullable($comment); return $this->touch(); }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function isPublished(): bool { return $this->publishedAt !== null; }

    public function publish(?\DateTimeImmutable $publishedAt = null): self
    {
        $this->publishedAt = $publishedAt ?? new \DateTimeImmutable();
        return $this->touch();
    }

    public function unpublish(): self
    {
        $this->publishedAt = null;
        return $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    private function normaliseNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
