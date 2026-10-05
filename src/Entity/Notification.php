<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_notif_source', fields: ['user', 'sourceType', 'sourceId'])]
#[ORM\Index(columns: ['user_id', 'read_at'], name: 'idx_notif_user_read')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $user = null;

    #[ORM\Column(length: 200)]
    private string $title = '';

    #[ORM\Column(length: 500)]
    private string $message = '';

    #[ORM\Column(length: 50)]
    private string $type = '';

    #[ORM\Column(length: 50)]
    private string $sourceType = '';

    #[ORM\Column]
    private int $sourceId = 0;

    #[ORM\Column(length: 20)]
    private string $priority = 'MEDIUM';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deepLink = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?Utilisateur { return $this->user; }
    public function setUser(Utilisateur $user): static { $this->user = $user; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $sourceType): static { $this->sourceType = $sourceType; return $this; }

    public function getSourceId(): int { return $this->sourceId; }
    public function setSourceId(int $sourceId): static { $this->sourceId = $sourceId; return $this; }

    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): static { $this->priority = $priority; return $this; }

    public function getDeepLink(): ?string { return $this->deepLink; }
    public function setDeepLink(?string $deepLink): static { $this->deepLink = $deepLink; return $this; }

    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $readAt): static { $this->readAt = $readAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isRead(): bool { return $this->readAt !== null; }
}
