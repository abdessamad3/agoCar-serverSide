<?php

namespace App\Entity;

use App\Repository\DepenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 30)]
    private ?string $typeDepense = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $datePaiment = null;

    #[ORM\Column]
    private ?\DateTime $creeAu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $editAu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?voiture $voiture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?bureau $bureau = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?utilisateur $creePar = null;

    #[ORM\ManyToOne(inversedBy: 'depense')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vidange $vidange = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTypeDepense(): ?string
    {
        return $this->typeDepense;
    }

    public function setTypeDepense(string $typeDepense): static
    {
        $this->typeDepense = $typeDepense;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDatePaiment(): ?\DateTime
    {
        return $this->datePaiment;
    }

    public function setDatePaiment(?\DateTime $datePaiment): static
    {
        $this->datePaiment = $datePaiment;

        return $this;
    }

    public function getCreeAu(): ?\DateTime
    {
        return $this->creeAu;
    }

    public function setCreeAu(\DateTime $creeAu): static
    {
        $this->creeAu = $creeAu;

        return $this;
    }

    public function getEditAu(): ?\DateTime
    {
        return $this->editAu;
    }

    public function setEditAu(?\DateTime $editAu): static
    {
        $this->editAu = $editAu;

        return $this;
    }

    public function getVoiture(): ?voiture
    {
        return $this->voiture;
    }

    public function setVoiture(?voiture $voiture): static
    {
        $this->voiture = $voiture;

        return $this;
    }

    public function getBureau(): ?bureau
    {
        return $this->bureau;
    }

    public function setBureau(?bureau $bureau): static
    {
        $this->bureau = $bureau;

        return $this;
    }

    public function getCreePar(): ?utilisateur
    {
        return $this->creePar;
    }

    public function setCreePar(?utilisateur $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    public function getVidange(): ?Vidange
    {
        return $this->vidange;
    }

    public function setVidange(?Vidange $vidange): static
    {
        $this->vidange = $vidange;

        return $this;
    }
}
