<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'error_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_error_log_created_at')]
#[ORM\Index(columns: ['source'], name: 'idx_error_log_source')]
class ErrorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** 'backend' | 'frontend' */
    #[ORM\Column(length: 20)]
    private string $source = 'backend';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $exceptionClass = null;

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(nullable: true)]
    private ?int $line = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $trace = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestUrl = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $requestMethod = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }

    public function getExceptionClass(): ?string { return $this->exceptionClass; }
    public function setExceptionClass(?string $v): static { $this->exceptionClass = $v; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): static { $this->message = $v; return $this; }

    public function getFile(): ?string { return $this->file; }
    public function setFile(?string $v): static { $this->file = $v; return $this; }

    public function getLine(): ?int { return $this->line; }
    public function setLine(?int $v): static { $this->line = $v; return $this; }

    public function getTrace(): ?string { return $this->trace; }
    public function setTrace(?string $v): static { $this->trace = $v; return $this; }

    public function getRequestUrl(): ?string { return $this->requestUrl; }
    public function setRequestUrl(?string $v): static { $this->requestUrl = $v; return $this; }

    public function getRequestMethod(): ?string { return $this->requestMethod; }
    public function setRequestMethod(?string $v): static { $this->requestMethod = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getUser(): ?Utilisateur { return $this->user; }
    public function setUser(?Utilisateur $v): static { $this->user = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): static { $this->ipAddress = $v; return $this; }
}
