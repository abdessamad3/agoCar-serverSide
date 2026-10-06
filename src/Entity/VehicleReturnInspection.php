<?php

namespace App\Entity;

use App\Repository\VehicleReturnInspectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleReturnInspectionRepository::class)]
class VehicleReturnInspection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Reservation $reservation;

    /** String enum: vide | quart | moitie | trois_quarts | plein */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $fuelLevelIn = null;

    #[ORM\Column(nullable: true)]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** clean | minor_damage | major_damage — `condition` is a reserved word in MySQL/MariaDB
     *  (used in stored-procedure DECLARE ... CONDITION), so it must stay backtick-quoted here to
     *  match how the column was created in the migration, or every INSERT/UPDATE breaks. */
    #[ORM\Column(name: '`condition`', length: 30, options: ['default' => 'clean'])]
    private string $condition = 'clean';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $fuelCharge = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $lateCharge = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $damageCharge = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $equipmentCharge = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $remiseMontant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remiseMotif = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cautionRemboursee = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureClientRetour = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureSocieteRetour = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $inspectedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $inspectedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\OneToMany(targetEntity: Damage::class, mappedBy: 'returnInspection', cascade: ['persist', 'remove'])]
    private Collection $damageItems;

    public function __construct()
    {
        $this->damageItems = new ArrayCollection();
    }

    /**
     * Billed days from pickup to this inspection. If the inspection was logged
     * more than a week past the planned return date, that gap is treated as a
     * retroactively-backfilled record (inspectedAt = data-entry time, not the
     * real return date) rather than genuine week+-long lateness, and billing
     * falls back to the planned end date instead. Real lateness up to a week
     * still bills from the actual inspectedAt.
     */
    public static function computeJoursFactures(
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
        ?\DateTimeImmutable $inspectedAt
    ): ?int {
        if (!$inspectedAt || !$dateDebut) return null;

        $referenceEnd = $inspectedAt;
        if ($dateFin) {
            $overdueSeconds = $inspectedAt->getTimestamp() - $dateFin->getTimestamp();
            if ($overdueSeconds > 7 * 86400) {
                $referenceEnd = $dateFin;
            }
        }

        $joursFactures = (int) ceil(($referenceEnd->getTimestamp() - $dateDebut->getTimestamp()) / 86400);

        if ($dateFin) {
            $overdue = $referenceEnd->getTimestamp() - $dateFin->getTimestamp();
            if ($overdue > 7200) {
                ++$joursFactures;
            }
        }

        return max(1, $joursFactures);
    }

    public function getId(): ?int { return $this->id; }

    public function getReservation(): Reservation { return $this->reservation; }
    public function setReservation(Reservation $r): static { $this->reservation = $r; return $this; }

    public function getFuelLevelIn(): ?string { return $this->fuelLevelIn; }
    public function setFuelLevelIn(?string $v): static { $this->fuelLevelIn = $v; return $this; }

    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $k): static { $this->kilometrage = $k; return $this; }

    public function getPhotos(): ?array { return $this->photos; }
    public function setPhotos(?array $p): static { $this->photos = $p; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): static { $this->notes = $n; return $this; }

    public function getCondition(): string { return $this->condition; }
    public function setCondition(string $c): static { $this->condition = $c; return $this; }

    public function getFuelCharge(): ?string { return $this->fuelCharge; }
    public function setFuelCharge(?string $v): static { $this->fuelCharge = $v; return $this; }

    public function getLateCharge(): ?string { return $this->lateCharge; }
    public function setLateCharge(?string $v): static { $this->lateCharge = $v; return $this; }

    public function getDamageCharge(): ?string { return $this->damageCharge; }
    public function setDamageCharge(?string $v): static { $this->damageCharge = $v; return $this; }

    public function getEquipmentCharge(): ?string { return $this->equipmentCharge; }
    public function setEquipmentCharge(?string $v): static { $this->equipmentCharge = $v; return $this; }

    public function getRemiseMontant(): ?string { return $this->remiseMontant; }
    public function setRemiseMontant(?string $v): static { $this->remiseMontant = $v; return $this; }

    public function getRemiseMotif(): ?string { return $this->remiseMotif; }
    public function setRemiseMotif(?string $v): static { $this->remiseMotif = $v; return $this; }

    public function getCautionRemboursee(): ?string { return $this->cautionRemboursee; }
    public function setCautionRemboursee(?string $v): static { $this->cautionRemboursee = $v; return $this; }

    public function getSignatureClientRetour(): ?string { return $this->signatureClientRetour; }
    public function setSignatureClientRetour(?string $v): static { $this->signatureClientRetour = $v; return $this; }

    public function getSignatureSocieteRetour(): ?string { return $this->signatureSocieteRetour; }
    public function setSignatureSocieteRetour(?string $v): static { $this->signatureSocieteRetour = $v; return $this; }

    public function getInspectedBy(): ?Utilisateur { return $this->inspectedBy; }
    public function setInspectedBy(?Utilisateur $u): static { $this->inspectedBy = $u; return $this; }

    public function getInspectedAt(): \DateTimeImmutable { return $this->inspectedAt; }
    public function setInspectedAt(\DateTimeImmutable $d): static { $this->inspectedAt = $d; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $d): static { $this->editAu = $d; return $this; }

    /** @return Collection<int, Damage> */
    public function getDamageItems(): Collection { return $this->damageItems; }

    public function addDamageItem(Damage $dmg): static
    {
        if (!$this->damageItems->contains($dmg)) {
            $this->damageItems->add($dmg);
            $dmg->setReturnInspection($this);
        }
        return $this;
    }

    public function removeDamageItem(Damage $dmg): static
    {
        $this->damageItems->removeElement($dmg);
        return $this;
    }

    public function getTotalAdditionalCharges(): float
    {
        return (float)($this->fuelCharge ?? 0)
             + (float)($this->lateCharge ?? 0)
             + (float)($this->damageCharge ?? 0)
             + (float)($this->equipmentCharge ?? 0);
    }

    public function getTotalSupplementaire(): float
    {
        return $this->getTotalAdditionalCharges() - (float)($this->remiseMontant ?? 0);
    }
}
