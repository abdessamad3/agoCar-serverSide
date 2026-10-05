<?php

namespace App\Entity;

use App\Repository\ParametresRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametresRepository::class)]
class Parametres
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bureau $bureau = null;

    // ── Localization ─────────────────────────────────────────────────────────
    #[ORM\Column(length: 5, nullable: true, options: ['default' => 'MAD'])]
    private ?string $currency = 'MAD';

    #[ORM\Column(length: 5, nullable: true, options: ['default' => 'fr'])]
    private ?string $language = 'fr';

    #[ORM\Column(length: 15, nullable: true, options: ['default' => 'DD/MM/YYYY'])]
    private ?string $dateFormat = 'DD/MM/YYYY';

    #[ORM\Column(length: 50, nullable: true, options: ['default' => 'Africa/Casablanca'])]
    private ?string $timezone = 'Africa/Casablanca';

    #[ORM\Column(length: 5, nullable: true, options: ['default' => 'km'])]
    private ?string $distanceUnit = 'km';

    #[ORM\Column(type: 'float', options: ['default' => 20])]
    private float $vatRate = 20;

    #[ORM\Column(length: 20, nullable: true, options: ['default' => 'TVA'])]
    private ?string $vatLabel = 'TVA';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $showVatBreakdown = true;

    // ── Rental Rules ─────────────────────────────────────────────────────────
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $minDuration = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 30])]
    private int $maxDuration = 30;

    #[ORM\Column(type: 'integer', options: ['default' => 90])]
    private int $advanceBookingLimit = 90;

    #[ORM\Column(length: 5, nullable: true, options: ['default' => '09:00'])]
    private ?string $defaultPickupTime = '09:00';

    #[ORM\Column(length: 5, nullable: true, options: ['default' => '09:00'])]
    private ?string $defaultReturnTime = '09:00';

    #[ORM\Column(type: 'float', options: ['default' => 1])]
    private float $gracePeriod = 1;

    #[ORM\Column(type: 'float', options: ['default' => 2000])]
    private float $defaultDeposit = 2000;

    #[ORM\Column(type: 'float', options: ['default' => 100])]
    private float $lateReturnFee = 100;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $requireDeposit = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $allowPartialPayments = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoGenerateContract = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoGenerateInvoice = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireSignature = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contractFooterNote = null;

    // ── Debt Rules ───────────────────────────────────────────────────────────
    /** A client with outstanding debt (sum of unpaid CLOSED reservations) at or above this
     *  amount gets a warning when staff try to book them again. Soft block by default — staff
     *  see the warning and need a manager/admin override to proceed; see debtBlockEnabled to
     *  turn the check off entirely for this bureau. */
    #[ORM\Column(type: 'float', options: ['default' => 1000])]
    private float $debtBlockThreshold = 1000;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $debtBlockEnabled = true;

    // ── Notifications ─────────────────────────────────────────────────────────
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifNewReservation = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifContractActivated = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifReturnOverdue = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifPaymentReceived = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifMaintenanceDue = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifInsuranceExpiry = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifInspectionDue = true;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $adminAlertEmail = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $operationsEmail = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $financeAlertEmail = null;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $v): static { $this->language = $v; return $this; }

    public function getDateFormat(): ?string { return $this->dateFormat; }
    public function setDateFormat(?string $v): static { $this->dateFormat = $v; return $this; }

    public function getTimezone(): ?string { return $this->timezone; }
    public function setTimezone(?string $v): static { $this->timezone = $v; return $this; }

    public function getDistanceUnit(): ?string { return $this->distanceUnit; }
    public function setDistanceUnit(?string $v): static { $this->distanceUnit = $v; return $this; }

    public function getVatRate(): float { return $this->vatRate; }
    public function setVatRate(float $v): static { $this->vatRate = $v; return $this; }

    public function getVatLabel(): ?string { return $this->vatLabel; }
    public function setVatLabel(?string $v): static { $this->vatLabel = $v; return $this; }

    public function isShowVatBreakdown(): bool { return $this->showVatBreakdown; }
    public function setShowVatBreakdown(bool $v): static { $this->showVatBreakdown = $v; return $this; }

    public function getMinDuration(): int { return $this->minDuration; }
    public function setMinDuration(int $v): static { $this->minDuration = $v; return $this; }

    public function getMaxDuration(): int { return $this->maxDuration; }
    public function setMaxDuration(int $v): static { $this->maxDuration = $v; return $this; }

    public function getAdvanceBookingLimit(): int { return $this->advanceBookingLimit; }
    public function setAdvanceBookingLimit(int $v): static { $this->advanceBookingLimit = $v; return $this; }

    public function getDefaultPickupTime(): ?string { return $this->defaultPickupTime; }
    public function setDefaultPickupTime(?string $v): static { $this->defaultPickupTime = $v; return $this; }

    public function getDefaultReturnTime(): ?string { return $this->defaultReturnTime; }
    public function setDefaultReturnTime(?string $v): static { $this->defaultReturnTime = $v; return $this; }

    public function getGracePeriod(): float { return $this->gracePeriod; }
    public function setGracePeriod(float $v): static { $this->gracePeriod = $v; return $this; }

    public function getDefaultDeposit(): float { return $this->defaultDeposit; }
    public function setDefaultDeposit(float $v): static { $this->defaultDeposit = $v; return $this; }

    public function getLateReturnFee(): float { return $this->lateReturnFee; }
    public function setLateReturnFee(float $v): static { $this->lateReturnFee = $v; return $this; }

    public function isRequireDeposit(): bool { return $this->requireDeposit; }
    public function setRequireDeposit(bool $v): static { $this->requireDeposit = $v; return $this; }

    public function isAllowPartialPayments(): bool { return $this->allowPartialPayments; }
    public function setAllowPartialPayments(bool $v): static { $this->allowPartialPayments = $v; return $this; }

    public function isAutoGenerateContract(): bool { return $this->autoGenerateContract; }
    public function setAutoGenerateContract(bool $v): static { $this->autoGenerateContract = $v; return $this; }

    public function isAutoGenerateInvoice(): bool { return $this->autoGenerateInvoice; }
    public function setAutoGenerateInvoice(bool $v): static { $this->autoGenerateInvoice = $v; return $this; }

    public function isRequireSignature(): bool { return $this->requireSignature; }
    public function setRequireSignature(bool $v): static { $this->requireSignature = $v; return $this; }

    public function getContractFooterNote(): ?string { return $this->contractFooterNote; }
    public function setContractFooterNote(?string $v): static { $this->contractFooterNote = $v; return $this; }

    public function getDebtBlockThreshold(): float { return $this->debtBlockThreshold; }
    public function setDebtBlockThreshold(float $v): static { $this->debtBlockThreshold = $v; return $this; }

    public function isDebtBlockEnabled(): bool { return $this->debtBlockEnabled; }
    public function setDebtBlockEnabled(bool $v): static { $this->debtBlockEnabled = $v; return $this; }

    public function isNotifNewReservation(): bool { return $this->notifNewReservation; }
    public function setNotifNewReservation(bool $v): static { $this->notifNewReservation = $v; return $this; }

    public function isNotifContractActivated(): bool { return $this->notifContractActivated; }
    public function setNotifContractActivated(bool $v): static { $this->notifContractActivated = $v; return $this; }

    public function isNotifReturnOverdue(): bool { return $this->notifReturnOverdue; }
    public function setNotifReturnOverdue(bool $v): static { $this->notifReturnOverdue = $v; return $this; }

    public function isNotifPaymentReceived(): bool { return $this->notifPaymentReceived; }
    public function setNotifPaymentReceived(bool $v): static { $this->notifPaymentReceived = $v; return $this; }

    public function isNotifMaintenanceDue(): bool { return $this->notifMaintenanceDue; }
    public function setNotifMaintenanceDue(bool $v): static { $this->notifMaintenanceDue = $v; return $this; }

    public function isNotifInsuranceExpiry(): bool { return $this->notifInsuranceExpiry; }
    public function setNotifInsuranceExpiry(bool $v): static { $this->notifInsuranceExpiry = $v; return $this; }

    public function isNotifInspectionDue(): bool { return $this->notifInspectionDue; }
    public function setNotifInspectionDue(bool $v): static { $this->notifInspectionDue = $v; return $this; }

    public function getAdminAlertEmail(): ?string { return $this->adminAlertEmail; }
    public function setAdminAlertEmail(?string $v): static { $this->adminAlertEmail = $v; return $this; }

    public function getOperationsEmail(): ?string { return $this->operationsEmail; }
    public function setOperationsEmail(?string $v): static { $this->operationsEmail = $v; return $this; }

    public function getFinanceAlertEmail(): ?string { return $this->financeAlertEmail; }
    public function setFinanceAlertEmail(?string $v): static { $this->financeAlertEmail = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'bureauId'              => $this->bureau?->getId(),
            'bureauNom'             => $this->bureau?->getNom(),
            'currency'              => $this->currency,
            'language'              => $this->language,
            'dateFormat'            => $this->dateFormat,
            'timezone'              => $this->timezone,
            'distanceUnit'          => $this->distanceUnit,
            'vatRate'               => $this->vatRate,
            'vatLabel'              => $this->vatLabel,
            'showVatBreakdown'      => $this->showVatBreakdown,
            'minDuration'           => $this->minDuration,
            'maxDuration'           => $this->maxDuration,
            'advanceBookingLimit'   => $this->advanceBookingLimit,
            'defaultPickupTime'     => $this->defaultPickupTime,
            'defaultReturnTime'     => $this->defaultReturnTime,
            'gracePeriod'           => $this->gracePeriod,
            'defaultDeposit'        => $this->defaultDeposit,
            'lateReturnFee'         => $this->lateReturnFee,
            'requireDeposit'        => $this->requireDeposit,
            'allowPartialPayments'  => $this->allowPartialPayments,
            'autoGenerateContract'  => $this->autoGenerateContract,
            'autoGenerateInvoice'   => $this->autoGenerateInvoice,
            'requireSignature'      => $this->requireSignature,
            'contractFooterNote'    => $this->contractFooterNote,
            'debtBlockThreshold'    => $this->debtBlockThreshold,
            'debtBlockEnabled'      => $this->debtBlockEnabled,
            'notifNewReservation'   => $this->notifNewReservation,
            'notifContractActivated'=> $this->notifContractActivated,
            'notifReturnOverdue'    => $this->notifReturnOverdue,
            'notifPaymentReceived'  => $this->notifPaymentReceived,
            'notifMaintenanceDue'   => $this->notifMaintenanceDue,
            'notifInsuranceExpiry'  => $this->notifInsuranceExpiry,
            'notifInspectionDue'    => $this->notifInspectionDue,
            'adminAlertEmail'       => $this->adminAlertEmail,
            'operationsEmail'       => $this->operationsEmail,
            'financeAlertEmail'     => $this->financeAlertEmail,
        ];
    }
}
