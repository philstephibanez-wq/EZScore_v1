<?php

declare(strict_types=1);

namespace App\Domain\Playlist;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'playlists')]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 140)]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16)]
    private string $ownerType = 'user';

    #[ORM\Column]
    private int $ownerId = 0;

    #[ORM\Column]
    private bool $public = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'CASCADE')]
    private User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description !== null ? trim($description) : null; return $this; }
    public function getOwnerType(): string { return $this->ownerType; }
    public function setOwnerType(string $type): self { $this->ownerType = $type === 'group' ? 'group' : 'user'; return $this; }
    public function getOwnerId(): int { return $this->ownerId; }
    public function setOwnerId(int $id): self { $this->ownerId = $id; return $this; }
    public function isPublic(): bool { return $this->public; }
    public function setPublic(bool $public): self { $this->public = $public; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): self { $this->createdBy = $user; return $this; }
}
