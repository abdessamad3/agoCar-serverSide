<?php

namespace App\Entity;

use App\Repository\HistoriquePaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriquePaiementRepository::class)]
class HistoriquePaiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, name: 'reservation_id')]
    private ?Reservation $reservation = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, name: 'montant')]
    private ?string $montant = null;

    #[ORM\Column(type: 'date_immutable', name: 'date_paiement')]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(length: 50, nullable: true, name: 'mode_paiement')]
    private ?string $modePaiement = null;

    #[ORM\Column(length: 255, nullable: true, name: 'note')]
    private ?string $note = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'cree_au')]
    private ?\DateTimeImmutable $creeAu = null;

    public function getId(): ?int { return $this->id; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }

    public function getDatePaiement(): ?\DateTimeImmutable { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeImmutable $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }

    public function getModePaiement(): ?string { return $this->modePaiement; }
    public function setModePaiement(?string $modePaiement): static { $this->modePaiement = $modePaiement; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
}
