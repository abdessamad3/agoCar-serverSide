<?php

namespace App\Entity;

use App\Enum\StatusEnum;
use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Reservation;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(length: 50, enumType: StatusEnum::class)]
    private ?StatusEnum $statut = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: Credit::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Credit $credit = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true, name: 'reservation_id', onDelete: 'SET NULL')]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }
    public function getDatePaiement(): ?\DateTimeImmutable { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeImmutable $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }
    public function getStatut(): ?StatusEnum { return $this->statut; }
    public function setStatut(StatusEnum $statut): static { $this->statut = $statut; return $this; }
    public function getModePaiement(): ?string { return $this->modePaiement; }
    public function setModePaiement(?string $modePaiement): static { $this->modePaiement = $modePaiement; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }
    public function getCredit(): ?Credit { return $this->credit; }
    public function setCredit(?Credit $credit): static { $this->credit = $credit; return $this; }
    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
}