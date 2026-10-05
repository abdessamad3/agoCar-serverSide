<?php

namespace App\Entity;

use App\Repository\AchatInstallmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchatInstallmentRepository::class)]
#[ORM\Index(columns: ['status'],   name: 'idx_ai_status')]
#[ORM\Index(columns: ['due_date'], name: 'idx_ai_due_date')]
class AchatInstallment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AchatVoiture::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AchatVoiture $achatVoiture;

    #[ORM\Column]
    private int $installmentNumber;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $amountPaid = '0.00';

    /** pending | paid | overdue | partial */
    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invoiceName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creeAu;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    public function getId(): ?int { return $this->id; }

    public function getAchatVoiture(): AchatVoiture { return $this->achatVoiture; }
    public function setAchatVoiture(AchatVoiture $achatVoiture): static { $this->achatVoiture = $achatVoiture; return $this; }

    public function getInstallmentNumber(): int { return $this->installmentNumber; }
    public function setInstallmentNumber(int $n): static { $this->installmentNumber = $n; return $this; }

    public function getDueDate(): \DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(\DateTimeImmutable $d): static { $this->dueDate = $d; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $a): static { $this->amount = $a; return $this; }

    public function getAmountPaid(): string { return $this->amountPaid; }
    public function setAmountPaid(string $a): static { $this->amountPaid = $a; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }

    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $d): static { $this->paidAt = $d; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): static { $this->notes = $n; return $this; }

    public function getInvoiceName(): ?string { return $this->invoiceName; }
    public function setInvoiceName(?string $n): static { $this->invoiceName = $n; return $this; }

    public function getCreeAu(): \DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $d): static { $this->creeAu = $d; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $d): static { $this->editAu = $d; return $this; }
}
