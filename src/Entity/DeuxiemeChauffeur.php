<?php

namespace App\Entity;

use App\Repository\DeuxiemeChauffeurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeuxiemeChauffeurRepository::class)]
class DeuxiemeChauffeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nationalite = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $cin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $permisConduite = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeImmutable $permisDelivreLe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $permisDelivreA = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseMaroc = null;

    #[ORM\Column(nullable: true)]
    private ?int $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseEtranger = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneEtranger = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $passeport = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeImmutable $passeportDelivreLe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passeportDelivreA = null;

    #[ORM\OneToOne(mappedBy: 'deuxiemeChauffeur', targetEntity: Reservation::class)]
    private ?Reservation $reservation = null;

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): static { $this->nom = $v; return $this; }

    public function getNationalite(): ?string { return $this->nationalite; }
    public function setNationalite(?string $v): static { $this->nationalite = $v; return $this; }

    public function getCin(): ?string { return $this->cin; }
    public function setCin(?string $v): static { $this->cin = $v; return $this; }

    public function getPermisConduite(): ?string { return $this->permisConduite; }
    public function setPermisConduite(?string $v): static { $this->permisConduite = $v; return $this; }

    public function getPermisDelivreLe(): ?\DateTimeImmutable { return $this->permisDelivreLe; }
    public function setPermisDelivreLe(?\DateTimeImmutable $v): static { $this->permisDelivreLe = $v; return $this; }

    public function getPermisDelivreA(): ?string { return $this->permisDelivreA; }
    public function setPermisDelivreA(?string $v): static { $this->permisDelivreA = $v; return $this; }

    public function getDateNaissance(): ?\DateTimeImmutable { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeImmutable $v): static { $this->dateNaissance = $v; return $this; }

    public function getAdresseMaroc(): ?string { return $this->adresseMaroc; }
    public function setAdresseMaroc(?string $v): static { $this->adresseMaroc = $v; return $this; }

    public function getTelephone(): ?int { return $this->telephone; }
    public function setTelephone(?int $v): static { $this->telephone = $v; return $this; }

    public function getAdresseEtranger(): ?string { return $this->adresseEtranger; }
    public function setAdresseEtranger(?string $v): static { $this->adresseEtranger = $v; return $this; }

    public function getTelephoneEtranger(): ?string { return $this->telephoneEtranger; }
    public function setTelephoneEtranger(?string $v): static { $this->telephoneEtranger = $v; return $this; }

    public function getPasseport(): ?string { return $this->passeport; }
    public function setPasseport(?string $v): static { $this->passeport = $v; return $this; }

    public function getPasseportDelivreLe(): ?\DateTimeImmutable { return $this->passeportDelivreLe; }
    public function setPasseportDelivreLe(?\DateTimeImmutable $v): static { $this->passeportDelivreLe = $v; return $this; }

    public function getPasseportDelivreA(): ?string { return $this->passeportDelivreA; }
    public function setPasseportDelivreA(?string $v): static { $this->passeportDelivreA = $v; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $v): static { $this->reservation = $v; return $this; }
}
