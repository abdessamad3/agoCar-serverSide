<?php

namespace App\Entity;

use App\Repository\SuiviTechniqueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuiviTechniqueRepository::class)]
class SuiviTechnique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Depense $depense = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Voiture $voiture = null;

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

    /** Inspection outcome: 'favorable' | 'favorable_avec_reserve' | 'defavorable' | null (pending/unknown) */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $resultat = null;

    public function getId(): ?int { return $this->id; }

    public function getDepense(): ?Depense { return $this->depense; }
    public function setDepense(?Depense $depense): static { $this->depense = $depense; return $this; }

    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $voiture): static { $this->voiture = $voiture; return $this; }
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
    public function getResultat(): ?string { return $this->resultat; }
    public function setResultat(?string $resultat): static { $this->resultat = $resultat; return $this; }
}
