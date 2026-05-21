<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cin = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $passeport = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $permisConduite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nationalite = null;

    #[ORM\Column(nullable: true)]
    private ?int $telephone = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getCin(): ?string { return $this->cin; }
    public function setCin(?string $cin): static { $this->cin = $cin; return $this; }
    public function getPasseport(): ?string { return $this->passeport; }
    public function setPasseport(?string $passeport): static { $this->passeport = $passeport; return $this; }
    public function getPermisConduite(): ?string { return $this->permisConduite; }
    public function setPermisConduite(?string $permisConduite): static { $this->permisConduite = $permisConduite; return $this; }
    public function getNationalite(): ?string { return $this->nationalite; }
    public function setNationalite(?string $nationalite): static { $this->nationalite = $nationalite; return $this; }
    public function getTelephone(): ?int { return $this->telephone; }
    public function setTelephone(?int $telephone): static { $this->telephone = $telephone; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
}