<?php

namespace App\Entity;

use App\Repository\VehicleCreditReminderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleCreditReminderRepository::class)]
#[ORM\Table(name: 'vehicle_credit_reminder')]
class VehicleCreditReminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleCredit::class, inversedBy: 'reminders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleCredit $vehicleCredit = null;

    #[ORM\ManyToOne(targetEntity: VehicleCreditInstallment::class, inversedBy: 'reminders')]
    private ?VehicleCreditInstallment $installment = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $reminderDate = null;

    #[ORM\Column(length: 30)]
    private ?string $reminderType = null; // 15_days, 7_days, 3_days, due_today, overdue

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending'; // pending, sent, failed

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVehicleCredit(): ?VehicleCredit { return $this->vehicleCredit; }
    public function setVehicleCredit(?VehicleCredit $v): static { $this->vehicleCredit = $v; return $this; }
    public function getInstallment(): ?VehicleCreditInstallment { return $this->installment; }
    public function setInstallment(?VehicleCreditInstallment $v): static { $this->installment = $v; return $this; }
    public function getReminderDate(): ?\DateTimeInterface { return $this->reminderDate; }
    public function setReminderDate(\DateTimeInterface $v): static { $this->reminderDate = $v; return $this; }
    public function getReminderType(): ?string { return $this->reminderType; }
    public function setReminderType(string $v): static { $this->reminderType = $v; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $v): static { $this->sentAt = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
