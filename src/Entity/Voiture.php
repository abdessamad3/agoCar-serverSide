<?php

namespace App\Entity;

use App\Repository\VoitureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoitureRepository::class)]
class Voiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private ?string $marque = null;

    #[ORM\Column(length: 60)]
    private ?string $modele = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\Column]
    private ?int $kilometrageActuel = null;

    #[ORM\Column(length: 30)]
    private ?string $typeCarburant = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column]
    private ?bool $climatisation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixJour = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prixAchat = null;

    #[ORM\Column(length: 30)]
    private ?string $voitureStatus = null;

    #[ORM\Column(length: 30)]
    private ?string $reservationStatus = null;

    #[ORM\Column]
    private ?\DateTime $creeAu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $editAu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?bureau $bureau = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $creePar = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getKilometrageActuel(): ?int
    {
        return $this->kilometrageActuel;
    }

    public function setKilometrageActuel(int $kilometrageActuel): static
    {
        $this->kilometrageActuel = $kilometrageActuel;

        return $this;
    }

    public function getTypeCarburant(): ?string
    {
        return $this->typeCarburant;
    }

    public function setTypeCarburant(string $typeCarburant): static
    {
        $this->typeCarburant = $typeCarburant;

        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function isClimatisation(): ?bool
    {
        return $this->climatisation;
    }

    public function setClimatisation(bool $climatisation): static
    {
        $this->climatisation = $climatisation;

        return $this;
    }

    public function getPrixJour(): ?string
    {
        return $this->prixJour;
    }

    public function setPrixJour(string $prixJour): static
    {
        $this->prixJour = $prixJour;

        return $this;
    }

    public function getPrixAchat(): ?string
    {
        return $this->prixAchat;
    }

    public function setPrixAchat(?string $prixAchat): static
    {
        $this->prixAchat = $prixAchat;

        return $this;
    }

    public function getVoitureStatus(): ?string
    {
        return $this->voitureStatus;
    }

    public function setVoitureStatus(string $voitureStatus): static
    {
        $this->voitureStatus = $voitureStatus;

        return $this;
    }

    public function getReservationStatus(): ?string
    {
        return $this->reservationStatus;
    }

    public function setReservationStatus(string $reservationStatus): static
    {
        $this->reservationStatus = $reservationStatus;

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

    public function getBureau(): ?bureau
    {
        return $this->bureau;
    }

    public function setBureau(?bureau $bureau): static
    {
        $this->bureau = $bureau;

        return $this;
    }

    public function getClient(): ?client
    {
        return $this->client;
    }

    public function setClient(?client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getCreePar(): ?Utilisateur
    {
        return $this->creePar;
    }

    public function setCreePar(?Utilisateur $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }
}
