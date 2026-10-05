<?php

namespace App\Entity;

use App\Enum\PaymentTypeEnum;
use App\Enum\PaymentMethodEnum;
use App\Repository\PaiementDepenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementDepenseRepository::class)]
class PaiementDepense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Depense::class)]
    #[ORM\JoinColumn(name: 'depense_id', referencedColumnName: 'id', nullable: true)]
    private ?Depense $depense = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(enumType: PaymentTypeEnum::class, nullable: true)]
    private ?PaymentTypeEnum $paymentType = PaymentTypeEnum::INSTALLMENT;

    #[ORM\Column(enumType: PaymentMethodEnum::class, nullable: true)]
    private ?PaymentMethodEnum $paymentMethod = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $paymentReference = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'edite_par_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $editePar = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'supprime_par_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $supprimePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getDepense(): ?Depense { return $this->depense; }
    public function setDepense(?Depense $depense): static { $this->depense = $depense; return $this; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }

    public function getDatePaiement(): ?\DateTimeImmutable { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeImmutable $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getPaymentType(): ?PaymentTypeEnum { return $this->paymentType; }
    public function setPaymentType(?PaymentTypeEnum $paymentType): static { $this->paymentType = $paymentType; return $this; }

    public function getPaymentMethod(): ?PaymentMethodEnum { return $this->paymentMethod; }
    public function setPaymentMethod(?PaymentMethodEnum $paymentMethod): static { $this->paymentMethod = $paymentMethod; return $this; }

    public function getPaymentReference(): ?string { return $this->paymentReference; }
    public function setPaymentReference(?string $paymentReference): static { $this->paymentReference = $paymentReference; return $this; }

    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }

    public function getEditePar(): ?Utilisateur { return $this->editePar; }
    public function setEditePar(?Utilisateur $editePar): static { $this->editePar = $editePar; return $this; }

    public function getSupprimePar(): ?Utilisateur { return $this->supprimePar; }
    public function setSupprimePar(?Utilisateur $supprimePar): static { $this->supprimePar = $supprimePar; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }

    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
}
