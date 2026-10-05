<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_entity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $entityType = '';

    #[ORM\Column]
    private int $entityId = 0;

    /** CREATE | UPDATE | DELETE | ARCHIVE */
    #[ORM\Column(length: 20)]
    private string $action = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $oldData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $newData = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Bureau $bureau = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $v): static { $this->entityType = $v; return $this; }

    public function getEntityId(): int { return $this->entityId; }
    public function setEntityId(int $v): static { $this->entityId = $v; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }

    public function getOldData(): ?array { return $this->oldData; }
    public function setOldData(?array $v): static { $this->oldData = $v; return $this; }

    public function getNewData(): ?array { return $this->newData; }
    public function setNewData(?array $v): static { $this->newData = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getUser(): ?Utilisateur { return $this->user; }
    public function setUser(?Utilisateur $v): static { $this->user = $v; return $this; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $v): static { $this->bureau = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): static { $this->ipAddress = $v; return $this; }
}
