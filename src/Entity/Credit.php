<?php

namespace App\Entity;

use App\Repository\CreditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @deprecated Superseded by {@see VehicleCredit}, which models the same
 * "vehicle financing contract" concept with a richer field set (financial
 * institution linkage, proper status lifecycle, installment/document/reminder
 * sub-entities) and is the only one of the two with a dashboard-stats endpoint.
 * Kept intact (not dropped) as the rollback source, same pattern as
 * HistoriquePaiement. Do not add new write paths to this entity.
 *
 * Note: AchatVoiture is a *different* concept (the vehicle purchase event
 * itself, including cash purchases with no financing at all) and is NOT
 * superseded by VehicleCredit — it remains the correct, actively-used entity
 * for recording how/when a vehicle was acquired.
 */
#[ORM\Entity(repositoryClass: CreditRepository::class)]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montantTotal = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $mensualite = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column]
    private ?int $dureeMois = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\OneToOne(targetEntity: Voiture::class)]
    private ?Voiture $voiture = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'credit')]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getMontantTotal(): ?string { return $this->montantTotal; }
    public function setMontantTotal(string $montantTotal): static { $this->montantTotal = $montantTotal; return $this; }
    public function getMensualite(): ?string { return $this->mensualite; }
    public function setMensualite(string $mensualite): static { $this->mensualite = $mensualite; return $this; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }
    public function getDureeMois(): ?int { return $this->dureeMois; }
    public function setDureeMois(int $dureeMois): static { $this->dureeMois = $dureeMois; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
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
    public function getPaiements(): Collection { return $this->paiements; }
    public function addPaiement(Paiement $paiement): static { if (!$this->paiements->contains($paiement)) { $this->paiements->add($paiement); $paiement->setCredit($this); } return $this; }
    public function removePaiement(Paiement $paiement): static { if ($this->paiements->removeElement($paiement)) { if ($paiement->getCredit() === $this) { $paiement->setCredit(null); } } return $this; }
}