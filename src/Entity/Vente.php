<?php

namespace App\Entity;

use App\Repository\VenteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VenteRepository::class)]
class Vente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Voiture $voiture;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateVente;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $prixVente;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $acheteur = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $benefice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Bureau $bureau = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creeAu;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getVoiture(): Voiture { return $this->voiture; }
    public function setVoiture(Voiture $voiture): static { $this->voiture = $voiture; return $this; }

    public function getDateVente(): \DateTimeImmutable { return $this->dateVente; }
    public function setDateVente(\DateTimeImmutable $dateVente): static { $this->dateVente = $dateVente; return $this; }

    public function getPrixVente(): string { return $this->prixVente; }
    public function setPrixVente(string $prixVente): static { $this->prixVente = $prixVente; return $this; }

    public function getAcheteur(): ?string { return $this->acheteur; }
    public function setAcheteur(?string $acheteur): static { $this->acheteur = $acheteur; return $this; }

    public function getBenefice(): ?string { return $this->benefice; }
    public function setBenefice(?string $benefice): static { $this->benefice = $benefice; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }

    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }

    public function getCreeAu(): \DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
}
