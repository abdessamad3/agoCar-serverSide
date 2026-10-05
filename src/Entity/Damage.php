<?php

namespace App\Entity;

use App\Repository\DamageRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Voiture;

#[ORM\Entity(repositoryClass: DamageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Damage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleDelivery::class, inversedBy: 'damages')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?VehicleDelivery $vehicleDelivery = null;

    #[ORM\ManyToOne(targetEntity: VehicleReturnInspection::class, inversedBy: 'damageItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?VehicleReturnInspection $returnInspection = null;

    /** Direct vehicle link for manually-recorded damage (not from rental return) */
    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Voiture $voiture = null;

    /** front | rear | left | right | roof | windshield */
    #[ORM\Column(length: 30)]
    private string $zone = 'front';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /** scratch | dent | crack | broken */
    #[ORM\Column(length: 30, options: ['default' => 'scratch'])]
    private string $severity = 'scratch';

    /** SVG x coordinate (percentage of diagram width) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $x = null;

    /** SVG y coordinate (percentage of diagram height) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $y = null;

    /** open | repaired */
    #[ORM\Column(length: 20, options: ['default' => 'open'])]
    private string $status = 'open';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $repairedAt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedCost = null;

    /** ID of the Reparation record that fixed this damage (set by RepairCompleted event) */
    #[ORM\Column(nullable: true)]
    private ?int $reparationId = null;

    /** ID of the Depense linked to this repair (for installment payment tracking) */
    #[ORM\Column(nullable: true)]
    private ?int $depenseId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->creeAu = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getVehicleDelivery(): ?VehicleDelivery { return $this->vehicleDelivery; }
    public function setVehicleDelivery(?VehicleDelivery $v): static { $this->vehicleDelivery = $v; return $this; }

    public function getReturnInspection(): ?VehicleReturnInspection { return $this->returnInspection; }
    public function setReturnInspection(?VehicleReturnInspection $v): static { $this->returnInspection = $v; return $this; }

    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $v): static { $this->voiture = $v; return $this; }

    public function getZone(): string { return $this->zone; }
    public function setZone(string $v): static { $this->zone = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $v): static { $this->photo = $v; return $this; }

    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $v): static { $this->severity = $v; return $this; }

    public function getX(): ?float { return $this->x; }
    public function setX(?float $v): static { $this->x = $v; return $this; }

    public function getY(): ?float { return $this->y; }
    public function setY(?float $v): static { $this->y = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getRepairedAt(): ?\DateTimeImmutable { return $this->repairedAt; }
    public function setRepairedAt(?\DateTimeImmutable $v): static { $this->repairedAt = $v; return $this; }

    public function getEstimatedCost(): ?string { return $this->estimatedCost; }
    public function setEstimatedCost(?string $v): static { $this->estimatedCost = $v; return $this; }

    public function getReparationId(): ?int { return $this->reparationId; }
    public function setReparationId(?int $v): static { $this->reparationId = $v; return $this; }

    public function getDepenseId(): ?int { return $this->depenseId; }
    public function setDepenseId(?int $v): static { $this->depenseId = $v; return $this; }
}
