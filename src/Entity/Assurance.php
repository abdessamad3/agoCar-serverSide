<?php

namespace App\Entity;

use App\Repository\AssuranceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssuranceRepository::class)]
class Assurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Depense $depense = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $compagnie = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeAssurance = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroContrat = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** Policy was explicitly voided/cancelled (distinct from archivedAt, which means "superseded by a renewal"). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function getId(): ?int { return $this->id; }
    public function getDepense(): ?Depense { return $this->depense; }
    public function setDepense(?Depense $depense): static { $this->depense = $depense; return $this; }
    public function getCompagnie(): ?string { return $this->compagnie; }
    public function setCompagnie(?string $compagnie): static { $this->compagnie = $compagnie; return $this; }
    public function getTypeAssurance(): ?string { return $this->typeAssurance; }
    public function setTypeAssurance(?string $typeAssurance): static { $this->typeAssurance = $typeAssurance; return $this; }
    public function getNumeroContrat(): ?string { return $this->numeroContrat; }
    public function setNumeroContrat(?string $numeroContrat): static { $this->numeroContrat = $numeroContrat; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
    public function getArchivedAt(): ?\DateTimeImmutable { return $this->archivedAt; }
    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static { $this->archivedAt = $archivedAt; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static { $this->cancelledAt = $cancelledAt; return $this; }
}