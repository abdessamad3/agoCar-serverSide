<?php

namespace App\Entity;

use App\Repository\BookingRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRequestRepository::class)]
#[ORM\Index(columns: ['voiture_id'], name: 'idx_booking_request_voiture')]
#[ORM\Index(columns: ['status'],     name: 'idx_booking_request_status')]
class BookingRequest
{
    public const STATUS_NEW       = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_DECLINED  = 'declined';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false, name: 'voiture_id')]
    private ?Voiture $voiture = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'date_debut')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'date_fin')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 150, name: 'full_name')]
    private ?string $fullName = null;

    #[ORM\Column(length: 30)]
    private ?string $phone = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true, name: 'lieu_livraison')]
    private ?string $lieuLivraison = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_NEW])]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(type: 'datetime_immutable', name: 'cree_au')]
    private ?\DateTimeImmutable $creeAu = null;

    public function getId(): ?int { return $this->id; }

    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $voiture): static { $this->voiture = $voiture; return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(string $fullName): static { $this->fullName = $fullName; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getLieuLivraison(): ?string { return $this->lieuLivraison; }
    public function setLieuLivraison(?string $lieuLivraison): static { $this->lieuLivraison = $lieuLivraison; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): static { $this->message = $message; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
}
