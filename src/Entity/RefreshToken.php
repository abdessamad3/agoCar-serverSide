<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $revoked = false;

    /** Set only when this token is revoked by rotation (a successful /auth/refresh call) — lets
     *  a benign race (two near-simultaneous refresh requests using the same token, e.g. two open
     *  tabs or a dev-server reload racing an in-flight request) follow the chain to the token
     *  that actually replaced it, instead of failing and force-logging the user out. Deliberate
     *  revocations (explicit logout, admin-forced logout) never set this, so they stay final. */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $replacedByToken = null;

    public function getId(): ?int { return $this->id; }

    public function getToken(): string { return $this->token; }
    public function setToken(string $token): static { $this->token = $token; return $this; }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function isRevoked(): bool { return $this->revoked; }
    public function setRevoked(bool $revoked): static { $this->revoked = $revoked; return $this; }

    public function getReplacedByToken(): ?string { return $this->replacedByToken; }
    public function setReplacedByToken(?string $replacedByToken): static { $this->replacedByToken = $replacedByToken; return $this; }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }
}
