<?php

namespace App\Entity;

use App\Repository\ContratRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContratRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_contrat_reservation', columns: ['reservation_id'])]
#[ORM\HasLifecycleCallbacks]
class Contrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true, nullable: true)]
    private ?string $numero = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasCaution = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cautionMontant = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $franchise = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $faitA = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    /** Price snapshot copied from Reservation.prixParJour at contract creation */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixParJourSnapshot = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbJoursFactures = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $remise = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $taxes = '0.00';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, name: 'reservation_id')]
    private ?Reservation $reservation = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->creeAu = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(?string $v): static { $this->numero = $v; return $this; }

    public function isHasCaution(): bool { return $this->hasCaution; }
    public function setHasCaution(bool $v): static { $this->hasCaution = $v; return $this; }

    public function getCautionMontant(): ?string { return $this->cautionMontant; }
    public function setCautionMontant(?string $v): static { $this->cautionMontant = $v; return $this; }

    public function getFranchise(): ?string { return $this->franchise; }
    public function setFranchise(?string $v): static { $this->franchise = $v; return $this; }

    public function getFaitA(): ?string { return $this->faitA; }
    public function setFaitA(?string $v): static { $this->faitA = $v; return $this; }

    public function getSignedAt(): ?\DateTimeImmutable { return $this->signedAt; }
    public function setSignedAt(?\DateTimeImmutable $v): static { $this->signedAt = $v; return $this; }

    public function getPrixParJourSnapshot(): ?string { return $this->prixParJourSnapshot; }
    public function setPrixParJourSnapshot(?string $v): static { $this->prixParJourSnapshot = $v; return $this; }

    public function getNbJoursFactures(): ?int { return $this->nbJoursFactures; }
    public function setNbJoursFactures(?int $v): static { $this->nbJoursFactures = $v; return $this; }

    public function getRemise(): string { return $this->remise; }
    public function setRemise(string $v): static { $this->remise = $v; return $this; }

    public function getTaxes(): string { return $this->taxes; }
    public function setTaxes(string $v): static { $this->taxes = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $v): static { $this->creeAu = $v; return $this; }

    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $v): static { $this->editAu = $v; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $v): static { $this->reservation = $v; return $this; }
}
