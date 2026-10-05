<?php

namespace App\Entity;

use App\Repository\VehicleCreditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleCreditRepository::class)]
#[ORM\Table(name: 'vehicle_credit')]
class VehicleCredit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Voiture $voiture = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    private ?Fournisseur $supplier = null;

    #[ORM\ManyToOne(targetEntity: FinancialInstitution::class, inversedBy: 'vehicleCredits')]
    private ?FinancialInstitution $financialInstitution = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contractNumber = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $vehiclePrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $downPayment = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $financedAmount = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $interestRate = '0';

    #[ORM\Column]
    private ?int $durationMonths = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $monthlyInstallment = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $totalCost = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $firstPaymentDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $dueDay = null; // day of month, 1-28

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $remainingBalance = null;

    #[ORM\Column(length: 30)]
    private ?string $status = 'draft'; // draft, pending_approval, active, completed, defaulted, cancelled

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $purchaseInvoiceNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: VehicleCreditInstallment::class, mappedBy: 'vehicleCredit', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['installmentNumber' => 'ASC'])]
    private Collection $installments;

    #[ORM\OneToMany(targetEntity: VehicleCreditPayment::class, mappedBy: 'vehicleCredit', cascade: ['persist', 'remove'])]
    private Collection $payments;

    #[ORM\OneToMany(targetEntity: VehicleCreditDocument::class, mappedBy: 'vehicleCredit', cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToMany(targetEntity: VehicleCreditReminder::class, mappedBy: 'vehicleCredit', cascade: ['persist', 'remove'])]
    private Collection $reminders;

    public function __construct()
    {
        $this->installments = new ArrayCollection();
        $this->payments     = new ArrayCollection();
        $this->documents    = new ArrayCollection();
        $this->reminders    = new ArrayCollection();
        $this->createdAt    = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $v): static { $this->voiture = $v; return $this; }
    public function getSupplier(): ?Fournisseur { return $this->supplier; }
    public function setSupplier(?Fournisseur $v): static { $this->supplier = $v; return $this; }
    public function getFinancialInstitution(): ?FinancialInstitution { return $this->financialInstitution; }
    public function setFinancialInstitution(?FinancialInstitution $v): static { $this->financialInstitution = $v; return $this; }
    public function getContractNumber(): ?string { return $this->contractNumber; }
    public function setContractNumber(?string $v): static { $this->contractNumber = $v; return $this; }
    public function getVehiclePrice(): ?string { return $this->vehiclePrice; }
    public function setVehiclePrice(string $v): static { $this->vehiclePrice = $v; return $this; }
    public function getDownPayment(): ?string { return $this->downPayment; }
    public function setDownPayment(string $v): static { $this->downPayment = $v; return $this; }
    public function getFinancedAmount(): ?string { return $this->financedAmount; }
    public function setFinancedAmount(string $v): static { $this->financedAmount = $v; return $this; }
    public function getInterestRate(): ?string { return $this->interestRate; }
    public function setInterestRate(string $v): static { $this->interestRate = $v; return $this; }
    public function getDurationMonths(): ?int { return $this->durationMonths; }
    public function setDurationMonths(int $v): static { $this->durationMonths = $v; return $this; }
    public function getMonthlyInstallment(): ?string { return $this->monthlyInstallment; }
    public function setMonthlyInstallment(string $v): static { $this->monthlyInstallment = $v; return $this; }
    public function getTotalCost(): ?string { return $this->totalCost; }
    public function setTotalCost(string $v): static { $this->totalCost = $v; return $this; }
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $v): static { $this->startDate = $v; return $this; }
    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $v): static { $this->endDate = $v; return $this; }
    public function getFirstPaymentDate(): ?\DateTimeInterface { return $this->firstPaymentDate; }
    public function setFirstPaymentDate(?\DateTimeInterface $v): static { $this->firstPaymentDate = $v; return $this; }
    public function getDueDay(): ?int { return $this->dueDay; }
    public function setDueDay(?int $v): static { $this->dueDay = $v; return $this; }
    public function getRemainingBalance(): ?string { return $this->remainingBalance; }
    public function setRemainingBalance(string $v): static { $this->remainingBalance = $v; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getPurchaseInvoiceNumber(): ?string { return $this->purchaseInvoiceNumber; }
    public function setPurchaseInvoiceNumber(?string $v): static { $this->purchaseInvoiceNumber = $v; return $this; }
    public function getPurchaseDate(): ?\DateTimeInterface { return $this->purchaseDate; }
    public function setPurchaseDate(?\DateTimeInterface $v): static { $this->purchaseDate = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $v): static { $this->updatedAt = $v; return $this; }
    public function getInstallments(): Collection { return $this->installments; }
    public function getPayments(): Collection { return $this->payments; }
    public function getDocuments(): Collection { return $this->documents; }
    public function getReminders(): Collection { return $this->reminders; }
}
