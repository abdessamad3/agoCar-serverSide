<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Voiture $voiture = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montantPaye = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montantRestant = null;

    #[ORM\Column]
    private bool $lavage = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\ManyToMany(targetEntity: Accessoire::class)]
    #[ORM\JoinTable(name: 'accessoire_reservation')]
    private Collection $accessoires;

    public function __construct()
    {
        $this->accessoires = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }
    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $voiture): static { $this->voiture = $voiture; return $this; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }
    public function getTotal(): ?string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }
    public function getMontantPaye(): ?string { return $this->montantPaye; }
    public function setMontantPaye(string $montantPaye): static { $this->montantPaye = $montantPaye; return $this; }
    public function getMontantRestant(): ?string { return $this->montantRestant; }
    public function setMontantRestant(string $montantRestant): static { $this->montantRestant = $montantRestant; return $this; }
    public function isLavage(): bool { return $this->lavage; }
    public function setLavage(bool $lavage): static { $this->lavage = $lavage; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getAccessoires(): Collection { return $this->accessoires; }
    public function addAccessoire(Accessoire $accessoire): static { if (!$this->accessoires->contains($accessoire)) { $this->accessoires->add($accessoire); } return $this; }
    public function removeAccessoire(Accessoire $accessoire): static { $this->accessoires->removeElement($accessoire); return $this; }
}