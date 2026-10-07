<?php

namespace App\Entity;

use App\Repository\TarifSaisonnierRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A date-range price adjustment (seasonal surcharge or discount) applied automatically
 * to a vehicle's daily rate when a reservation's start date falls inside the range.
 * See ReservationPricingService::resolveDailyRate() for how this is applied.
 */
#[ORM\Entity(repositoryClass: TarifSaisonnierRepository::class)]
#[ORM\Index(columns: ['date_debut'], name: 'idx_tarif_saisonnier_date_debut')]
#[ORM\Index(columns: ['date_fin'],   name: 'idx_tarif_saisonnier_date_fin')]
#[ORM\Index(columns: ['bureau_id'],  name: 'idx_tarif_saisonnier_bureau')]
class TarifSaisonnier
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED      = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'date_immutable', name: 'date_debut')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable', name: 'date_fin')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 20, name: 'type_ajustement')]
    private ?string $typeAjustement = self::TYPE_PERCENTAGE;

    /** Signed: negative = discount, positive = surcharge. Either a percentage of the
     *  base daily rate, or a flat MAD amount, per typeAjustement. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $valeur = null;

    /** Null = applies to every bureau. Set = only vehicles in that bureau. */
    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: true, name: 'bureau_id', onDelete: 'SET NULL')]
    private ?Bureau $bureau = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(string $v): static { $this->libelle = $v; return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $v): static { $this->dateDebut = $v; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $v): static { $this->dateFin = $v; return $this; }

    public function getTypeAjustement(): ?string { return $this->typeAjustement; }
    public function setTypeAjustement(string $v): static { $this->typeAjustement = $v; return $this; }

    public function getValeur(): ?string { return $this->valeur; }
    public function setValeur(string $v): static { $this->valeur = $v; return $this; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $v): static { $this->bureau = $v; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): static { $this->actif = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $v): static { $this->creeAu = $v; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $v): static { $this->deletedAt = $v; return $this; }

    /** Applies this rule's adjustment to a base daily rate, floored at 0. */
    public function applyTo(float $baseRate): float
    {
        $adjusted = $this->typeAjustement === self::TYPE_PERCENTAGE
            ? $baseRate * (1 + ((float) $this->valeur) / 100)
            : $baseRate + (float) $this->valeur;
        return max(0.0, $adjusted);
    }
}
