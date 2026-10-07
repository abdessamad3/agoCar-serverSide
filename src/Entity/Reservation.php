<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Bureau;
use App\Entity\Paiement;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Index(columns: ['date_debut'],         name: 'idx_reservation_date_debut')]
#[ORM\Index(columns: ['date_fin'],           name: 'idx_reservation_date_fin')]
#[ORM\Index(columns: ['client_id'],          name: 'idx_reservation_client')]
#[ORM\Index(columns: ['reservation_status'], name: 'idx_res_status')]
#[ORM\Index(columns: ['bureau_id'],          name: 'idx_reservation_bureau')]
class Reservation
{
    public const PAYMENT_UNPAID   = 'unpaid';
    public const PAYMENT_PARTIAL  = 'partial';
    public const PAYMENT_PAID     = 'paid';
    public const PAYMENT_OVERPAID = 'overpaid';

    /** Statuses where the rental is finished and its total/charges are final — only these
     *  count toward a client's outstanding debt (an in-progress rental's balance is still
     *  fluid: late fees and damage charges aren't assessed until return). */
    public const CLOSED_STATUSES = ['terminee', 'termine_avant_terme'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'date_debut')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'date_fin')]
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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuLivraison = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuRetour = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixParJour = null;

    /** One-off discount applied to this specific reservation (never a reason-less edit to
     *  total directly) — same shape as VehicleReturnInspection::remiseMontant/remiseMotif. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true, name: 'remise_montant')]
    private ?string $remiseMontant = null;

    #[ORM\Column(length: 255, nullable: true, name: 'remise_motif')]
    private ?string $remiseMotif = null;

    #[ORM\OneToOne(targetEntity: DeuxiemeChauffeur::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, name: 'deuxieme_chauffeur_id')]
    private ?DeuxiemeChauffeur $deuxiemeChauffeur = null;

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: true, name: 'bureau_id', onDelete: 'SET NULL')]
    private ?Bureau $bureau = null;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'reservation')]
    private Collection $paiements;

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
        $this->paiements   = new ArrayCollection();
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

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }

    public function getLieuLivraison(): ?string { return $this->lieuLivraison; }
    public function setLieuLivraison(?string $v): static { $this->lieuLivraison = $v; return $this; }

    public function getLieuRetour(): ?string { return $this->lieuRetour; }
    public function setLieuRetour(?string $v): static { $this->lieuRetour = $v; return $this; }

    public function getPrixParJour(): ?string { return $this->prixParJour; }
    public function setPrixParJour(?string $v): static { $this->prixParJour = $v; return $this; }

    public function getRemiseMontant(): ?string { return $this->remiseMontant; }
    public function setRemiseMontant(?string $v): static { $this->remiseMontant = $v; return $this; }

    public function getRemiseMotif(): ?string { return $this->remiseMotif; }
    public function setRemiseMotif(?string $v): static { $this->remiseMotif = $v; return $this; }

    public function getDeuxiemeChauffeur(): ?DeuxiemeChauffeur { return $this->deuxiemeChauffeur; }
    public function setDeuxiemeChauffeur(?DeuxiemeChauffeur $v): static { $this->deuxiemeChauffeur = $v; return $this; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }

    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection { return $this->paiements; }

    // ── Computed financial values — derived from total/montantPaye, never stored, so they
    //    can never drift out of sync with the numbers they're computed from. ──────────────

    /** Signed balance: negative = client owes money, positive = company owes a refund, 0 = settled. */
    public function getBalance(): float
    {
        return (float) ($this->montantPaye ?? 0) - (float) ($this->total ?? 0);
    }

    /** Outstanding amount still owed by the client — never negative (use getBalance() for refunds). */
    public function getMontantRestant(): float
    {
        return max(0.0, -$this->getBalance());
    }

    /** Amount owed back to the client when they've paid more than the total. */
    public function getMontantSurpaye(): float
    {
        return max(0.0, $this->getBalance());
    }

    public function getPaymentStatus(): string
    {
        $paid = (float) ($this->montantPaye ?? 0);
        if ($paid <= 0)                 return self::PAYMENT_UNPAID;
        $balance = $this->getBalance();
        if ($balance > 0)               return self::PAYMENT_OVERPAID;
        if ($balance < 0)               return self::PAYMENT_PARTIAL;
        return self::PAYMENT_PAID;
    }

    /** True once this reservation is in a final state where its total/charges won't change again. */
    public function isClosed(): bool
    {
        return in_array($this->reservationStatus, self::CLOSED_STATUSES, true);
    }
}