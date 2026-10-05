<?php

namespace App\Entity;

use App\Repository\VignetteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VignetteRepository::class)]
class Vignette
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Depense $depense = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $filePath = null;

    public function getId(): ?int { return $this->id; }
    public function getDepense(): ?Depense { return $this->depense; }
    public function setDepense(?Depense $depense): static { $this->depense = $depense; return $this; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }
}