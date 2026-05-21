<?php

namespace App\Entity;

use App\Repository\DepenseRepository;
use App\Enum\StatusEnum;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 50)]
    private ?string $typeDepense = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    // #[ORM\Column(length: 50)]
    // private ?string $statut = null;

    #[ORM\Column(enumType: StatusEnum::class)]
    private ?StatusEnum $statut = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class, inversedBy: 'depenses')]
    private ?Voiture $voiture = null;

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    private ?Bureau $bureau = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    public function getId(): ?int { return $this->id; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }
    public function getTypeDepense(): ?string { return $this->typeDepense; }
    public function setTypeDepense(string $typeDepense): static { $this->typeDepense = $typeDepense; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }
    // public function getStatut(): ?string { return $this->statut; }
    // public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getStatut(): ?StatusEnum{ return $this->statut; }
    public function setStatut(StatusEnum $statut): static{ $this->statut = $statut;  return $this; }
    public function getDatePaiement(): ?\DateTimeImmutable { return $this->datePaiement; }
    public function setDatePaiement(?\DateTimeImmutable $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }
    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $voiture): static { $this->voiture = $voiture; return $this; }
    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
}