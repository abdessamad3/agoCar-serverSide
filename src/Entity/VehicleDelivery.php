<?php

namespace App\Entity;

use App\Repository\VehicleDeliveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleDeliveryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class VehicleDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\Column(length: 30, options: ['default' => 'vide'])]
    private string $fuelLevelOut = 'vide';

    #[ORM\Column(nullable: true)]
    private ?int $mileageOut = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasExtincteur = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasLavage = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasPlaqueDepannage = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasCric = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasGilet = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasRoueSecours = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasSiegeBebe = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasTriangle = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $equipementNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $deliveryNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureClientDepart = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureDeuxiemeChauffeur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureSocieteDepart = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\OneToMany(targetEntity: Damage::class, mappedBy: 'vehicleDelivery', cascade: ['persist', 'remove'])]
    private Collection $damages;

    public function __construct()
    {
        $this->damages = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->creeAu = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $r): static { $this->reservation = $r; return $this; }

    public function getFuelLevelOut(): string { return $this->fuelLevelOut; }
    public function setFuelLevelOut(string $v): static { $this->fuelLevelOut = $v; return $this; }

    public function getMileageOut(): ?int { return $this->mileageOut; }
    public function setMileageOut(?int $v): static { $this->mileageOut = $v; return $this; }

    public function isHasExtincteur(): bool { return $this->hasExtincteur; }
    public function setHasExtincteur(bool $v): static { $this->hasExtincteur = $v; return $this; }

    public function isHasLavage(): bool { return $this->hasLavage; }
    public function setHasLavage(bool $v): static { $this->hasLavage = $v; return $this; }

    public function isHasPlaqueDepannage(): bool { return $this->hasPlaqueDepannage; }
    public function setHasPlaqueDepannage(bool $v): static { $this->hasPlaqueDepannage = $v; return $this; }

    public function isHasCric(): bool { return $this->hasCric; }
    public function setHasCric(bool $v): static { $this->hasCric = $v; return $this; }

    public function isHasGilet(): bool { return $this->hasGilet; }
    public function setHasGilet(bool $v): static { $this->hasGilet = $v; return $this; }

    public function isHasRoueSecours(): bool { return $this->hasRoueSecours; }
    public function setHasRoueSecours(bool $v): static { $this->hasRoueSecours = $v; return $this; }

    public function isHasSiegeBebe(): bool { return $this->hasSiegeBebe; }
    public function setHasSiegeBebe(bool $v): static { $this->hasSiegeBebe = $v; return $this; }

    public function isHasTriangle(): bool { return $this->hasTriangle; }
    public function setHasTriangle(bool $v): static { $this->hasTriangle = $v; return $this; }

    public function getEquipementNotes(): ?string { return $this->equipementNotes; }
    public function setEquipementNotes(?string $v): static { $this->equipementNotes = $v; return $this; }

    public function getDeliveryNotes(): ?string { return $this->deliveryNotes; }
    public function setDeliveryNotes(?string $v): static { $this->deliveryNotes = $v; return $this; }

    public function getSignatureClientDepart(): ?string { return $this->signatureClientDepart; }
    public function setSignatureClientDepart(?string $v): static { $this->signatureClientDepart = $v; return $this; }

    public function getSignatureDeuxiemeChauffeur(): ?string { return $this->signatureDeuxiemeChauffeur; }
    public function setSignatureDeuxiemeChauffeur(?string $v): static { $this->signatureDeuxiemeChauffeur = $v; return $this; }

    public function getSignatureSocieteDepart(): ?string { return $this->signatureSocieteDepart; }
    public function setSignatureSocieteDepart(?string $v): static { $this->signatureSocieteDepart = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $v): static { $this->creeAu = $v; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $v): static { $this->editAu = $v; return $this; }

    /** @return Collection<int, Damage> */
    public function getDamages(): Collection { return $this->damages; }

    public function addDamage(Damage $damage): static
    {
        if (!$this->damages->contains($damage)) {
            $this->damages->add($damage);
            $damage->setVehicleDelivery($this);
        }
        return $this;
    }

    public function removeDamage(Damage $damage): static
    {
        $this->damages->removeElement($damage);
        return $this;
    }
}
