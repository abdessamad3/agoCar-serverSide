<?php

namespace App\Entity;

use App\Repository\EmailLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailLogRepository::class)]
#[ORM\Table(name: 'email_log')]
class EmailLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(length: 255)]
    private string $recipientEmail;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column]
    private int $totalAlerts = 0;

    #[ORM\Column]
    private int $complianceCount = 0;

    #[ORM\Column]
    private int $oilCount = 0;

    #[ORM\Column]
    private int $creditCount = 0;

    #[ORM\Column(length: 20)]
    private string $status = 'sent';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 30)]
    private string $triggeredBy = 'scheduler';

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(\DateTimeImmutable $v): static { $this->sentAt = $v; return $this; }

    public function getRecipientEmail(): string { return $this->recipientEmail; }
    public function setRecipientEmail(string $v): static { $this->recipientEmail = $v; return $this; }

    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $v): static { $this->subject = $v; return $this; }

    public function getTotalAlerts(): int { return $this->totalAlerts; }
    public function setTotalAlerts(int $v): static { $this->totalAlerts = $v; return $this; }

    public function getComplianceCount(): int { return $this->complianceCount; }
    public function setComplianceCount(int $v): static { $this->complianceCount = $v; return $this; }

    public function getOilCount(): int { return $this->oilCount; }
    public function setOilCount(int $v): static { $this->oilCount = $v; return $this; }

    public function getCreditCount(): int { return $this->creditCount; }
    public function setCreditCount(int $v): static { $this->creditCount = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }

    public function getTriggeredBy(): string { return $this->triggeredBy; }
    public function setTriggeredBy(string $v): static { $this->triggeredBy = $v; return $this; }
}
