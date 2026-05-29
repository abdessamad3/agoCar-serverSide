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

    #[ORM\Column(type: 'date_immutable', name: 'date_debut')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable', name: 'date_fin')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, name: 'total')]
    private ?string $total = null;

    #[ORM\Column(length: 50, options: ['default' => 'confirmed'], name: 'reservation_status')]
    private ?string $reservationStatus = 'confirmed';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'], name: 'montant_paye')]
    private ?string $montantPaye = '0.00';

    #[ORM\Column(length: 50, nullable: true, name: 'mode_paiement')]
    private ?string $modePaiement = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'cree_au')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'edit_au')]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, name: 'client_id')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false, name: 'voiture_id')]
    private ?Voiture $voiture = null;

    /**
     * Bidirectional many-to-many: Reservation is the OWNING side
     * Accessoire is the INVERSE side (mappedBy on Accessoire)
     */
    #[ORM\ManyToMany(targetEntity: Accessoire::class, inversedBy: 'reservations')]
    #[ORM\JoinTable(name: 'reservation_accessoire')]
    private Collection $accessoires;

    public function __construct()
    {
        $this->accessoires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
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

    public function getCreeAu(): ?\DateTimeImmutable
    {
        return $this->creeAu;
    }

    public function setCreeAu(\DateTimeImmutable $creeAu): static
    {
        $this->creeAu = $creeAu;
        return $this;
    }

    public function getEditAu(): ?\DateTimeImmutable
    {
        return $this->editAu;
    }

    public function setEditAu(?\DateTimeImmutable $editAu): static
    {
        $this->editAu = $editAu;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getVoiture(): ?Voiture
    {
        return $this->voiture;
    }

    public function setVoiture(?Voiture $voiture): static
    {
        $this->voiture = $voiture;
        return $this;
    }

    /**
     * @return Collection<int, Accessoire>
     */
    public function getAccessoires(): Collection
    {
        return $this->accessoires;
    }

    public function addAccessoire(Accessoire $accessoire): static
    {
        if (!$this->accessoires->contains($accessoire)) {
            $this->accessoires->add($accessoire);
            $accessoire->addReservation($this);
        }
        return $this;
    }

    public function removeAccessoire(Accessoire $accessoire): static
    {
        if ($this->accessoires->removeElement($accessoire)) {
            $accessoire->removeReservation($this);
        }
        return $this;
    }

    public function getMontantPaye(): ?string
    {
        return $this->montantPaye;
    }

    public function setMontantPaye(string $montantPaye): static
    {
        $this->montantPaye = $montantPaye;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }
}