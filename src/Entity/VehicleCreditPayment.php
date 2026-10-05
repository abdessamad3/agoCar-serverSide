<?php

namespace App\Entity;

use App\Repository\VehicleCreditPaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleCreditPaymentRepository::class)]
#[ORM\Table(name: 'vehicle_credit_payment')]
class VehicleCreditPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleCredit::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleCredit $vehicleCredit = null;

    #[ORM\ManyToOne(targetEntity: VehicleCreditInstallment::class, inversedBy: 'payments')]
    private ?VehicleCreditInstallment $installment = null;

    #[ORM\Column(length: 30)]
    private ?string $paymentType = 'scheduled'; // scheduled, partial, advance, extra, settlement, refund

    #[ORM\Column(length: 30)]
    private ?string $paymentMethod = 'bank_transfer'; // bank_transfer, check, cash, direct_debit, other

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: PaymentAttachment::class, mappedBy: 'payment', cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->createdAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVehicleCredit(): ?VehicleCredit { return $this->vehicleCredit; }
    public function setVehicleCredit(?VehicleCredit $v): static { $this->vehicleCredit = $v; return $this; }
    public function getInstallment(): ?VehicleCreditInstallment { return $this->installment; }
    public function setInstallment(?VehicleCreditInstallment $v): static { $this->installment = $v; return $this; }
    public function getPaymentType(): ?string { return $this->paymentType; }
    public function setPaymentType(string $v): static { $this->paymentType = $v; return $this; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(string $v): static { $this->paymentMethod = $v; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $v): static { $this->amount = $v; return $this; }
    public function getPaymentDate(): ?\DateTimeInterface { return $this->paymentDate; }
    public function setPaymentDate(\DateTimeInterface $v): static { $this->paymentDate = $v; return $this; }
    public function getReferenceNumber(): ?string { return $this->referenceNumber; }
    public function setReferenceNumber(?string $v): static { $this->referenceNumber = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getAttachments(): Collection { return $this->attachments; }
}
