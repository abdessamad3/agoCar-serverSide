<?php

namespace App\Entity;

use App\Repository\RecurringExpenseTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecurringExpenseTemplateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_recurring_bureau_type', fields: ['bureau', 'typeDepense'])]
class RecurringExpenseTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bureau $bureau = null;

    /** loyer | salaire | telephone | electricite | eau | internet | vignette */
    #[ORM\Column(length: 50)]
    private string $typeDepense = '';

    /** monthly | yearly */
    #[ORM\Column(length: 20)]
    private string $frequency = 'monthly';

    /** fixed | variable */
    #[ORM\Column(length: 20)]
    private string $priceType = 'fixed';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $fixedAmount = null;

    #[ORM\Column(nullable: true)]
    private ?int $startPeriodMonth = null;

    #[ORM\Column]
    private int $startPeriodYear = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }

    public function getTypeDepense(): string { return $this->typeDepense; }
    public function setTypeDepense(string $typeDepense): static { $this->typeDepense = $typeDepense; return $this; }

    public function getFrequency(): string { return $this->frequency; }
    public function setFrequency(string $frequency): static { $this->frequency = $frequency; return $this; }

    public function getPriceType(): string { return $this->priceType; }
    public function setPriceType(string $priceType): static { $this->priceType = $priceType; return $this; }

    public function getFixedAmount(): ?string { return $this->fixedAmount; }
    public function setFixedAmount(?string $fixedAmount): static { $this->fixedAmount = $fixedAmount; return $this; }

    public function getStartPeriodMonth(): ?int { return $this->startPeriodMonth; }
    public function setStartPeriodMonth(?int $startPeriodMonth): static { $this->startPeriodMonth = $startPeriodMonth; return $this; }

    public function getStartPeriodYear(): int { return $this->startPeriodYear; }
    public function setStartPeriodYear(int $startPeriodYear): static { $this->startPeriodYear = $startPeriodYear; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
