<?php

namespace App\Entity;

use App\Repository\VidangeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VidangeRepository::class)]
class Vidange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Depense $depense = null;

    #[ORM\Column]
    private ?int $kilometrageSuivant = null;

    #[ORM\Column]
    private bool $filtreAir = false;

    #[ORM\Column]
    private bool $filtreHuile = false;

    #[ORM\Column]
    private bool $filtreCarburant = false;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    public function getId(): ?int { return $this->id; }
    public function getDepense(): ?Depense { return $this->depense; }
    public function setDepense(?Depense $depense): static { $this->depense = $depense; return $this; }
    public function getKilometrageSuivant(): ?int { return $this->kilometrageSuivant; }
    public function setKilometrageSuivant(int $kilometrageSuivant): static { $this->kilometrageSuivant = $kilometrageSuivant; return $this; }
    public function isFiltreAir(): bool { return $this->filtreAir; }
    public function setFiltreAir(bool $filtreAir): static { $this->filtreAir = $filtreAir; return $this; }
    public function isFiltreHuile(): bool { return $this->filtreHuile; }
    public function setFiltreHuile(bool $filtreHuile): static { $this->filtreHuile = $filtreHuile; return $this; }
    public function isFiltreCarburant(): bool { return $this->filtreCarburant; }
    public function setFiltreCarburant(bool $filtreCarburant): static { $this->filtreCarburant = $filtreCarburant; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
}