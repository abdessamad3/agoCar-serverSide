<?php

namespace App\Entity;

use App\Repository\VehicleCreditInstallmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleCreditInstallmentRepository::class)]
#[ORM\Table(name: 'vehicle_credit_installment')]
#[ORM\Index(columns: ['status'],   name: 'idx_vci_status')]
#[ORM\Index(columns: ['due_date'], name: 'idx_vci_due_date')]
#[ORM\Index(columns: ['vehicle_credit_id'], name: 'IDX_842EDF6C39B96AAB')]
class VehicleCreditInstallment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleCredit::class, inversedBy: 'installments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleCredit $vehicleCredit = null;

    #[ORM\Column]
    private ?int $installmentNumber = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $principalAmount = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $interestAmount = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $amountDue = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $amountPaid = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $remainingAmount = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending'; // pending, due_soon, due_today, partial, paid, overdue

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: VehicleCreditPayment::class, mappedBy: 'installment')]
    private Collection $payments;

    #[ORM\OneToMany(targetEntity: VehicleCreditReminder::class, mappedBy: 'installment', cascade: ['persist', 'remove'])]
    private Collection $reminders;

    public function __construct()
    {
        $this->payments  = new ArrayCollection();
        $this->reminders = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVehicleCredit(): ?VehicleCredit { return $this->vehicleCredit; }
    public function setVehicleCredit(?VehicleCredit $v): static { $this->vehicleCredit = $v; return $this; }
    public function getInstallmentNumber(): ?int { return $this->installmentNumber; }
    public function setInstallmentNumber(int $v): static { $this->installmentNumber = $v; return $this; }
    public function getDueDate(): ?\DateTimeInterface { return $this->dueDate; }
    public function setDueDate(\DateTimeInterface $v): static { $this->dueDate = $v; return $this; }
    public function getPrincipalAmount(): ?string { return $this->principalAmount; }
    public function setPrincipalAmount(string $v): static { $this->principalAmount = $v; return $this; }
    public function getInterestAmount(): ?string { return $this->interestAmount; }
    public function setInterestAmount(string $v): static { $this->interestAmount = $v; return $this; }
    public function getAmountDue(): ?string { return $this->amountDue; }
    public function setAmountDue(string $v): static { $this->amountDue = $v; return $this; }
    public function getAmountPaid(): ?string { return $this->amountPaid; }
    public function setAmountPaid(string $v): static { $this->amountPaid = $v; return $this; }
    public function getRemainingAmount(): ?string { return $this->remainingAmount; }
    public function setRemainingAmount(string $v): static { $this->remainingAmount = $v; return $this; }
    public function getPaidAt(): ?\DateTimeInterface { return $this->paidAt; }
    public function setPaidAt(?\DateTimeInterface $v): static { $this->paidAt = $v; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getPayments(): Collection { return $this->payments; }
    public function getReminders(): Collection { return $this->reminders; }
}
