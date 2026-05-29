<?php

namespace App\Entity;

use App\Repository\AchatVoitureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchatVoitureRepository::class)]
class AchatVoiture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Voiture::class)]
    private ?Voiture $voiture = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateAchat = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $prixAchat = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $apport = null;

    #[ORM\Column(length: 20)]
    private ?string $typeFinancement = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $mensualite = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $tauxInteret = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebutCredit = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $resteAFinancer = null;

    #[ORM\Column(nullable: true)]
    private ?int $dureeMois = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $dernierMensualite = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    public function getId(): ?int { return $this->id; }
    public function getVoiture(): ?Voiture { return $this->voiture; }
    public function setVoiture(?Voiture $voiture): static { $this->voiture = $voiture; return $this; }
    public function getFournisseur(): ?Fournisseur { return $this->fournisseur; }
    public function setFournisseur(?Fournisseur $fournisseur): static { $this->fournisseur = $fournisseur; return $this; }
    public function getDateAchat(): ?\DateTimeImmutable { return $this->dateAchat; }
    public function setDateAchat(\DateTimeImmutable $dateAchat): static { $this->dateAchat = $dateAchat; return $this; }
    public function getPrixAchat(): ?string { return $this->prixAchat; }
    public function setPrixAchat(string $prixAchat): static { $this->prixAchat = $prixAchat; return $this; }
    public function getApport(): ?string { return $this->apport; }
    public function setApport(?string $apport): static { $this->apport = $apport; return $this; }
    public function getTypeFinancement(): ?string { return $this->typeFinancement; }
    public function setTypeFinancement(string $typeFinancement): static { $this->typeFinancement = $typeFinancement; return $this; }
    public function getMensualite(): ?string { return $this->mensualite; }
    public function setMensualite(?string $mensualite): static { $this->mensualite = $mensualite; return $this; }
    public function getTauxInteret(): ?string { return $this->tauxInteret; }
    public function setTauxInteret(?string $tauxInteret): static { $this->tauxInteret = $tauxInteret; return $this; }
    public function getDateDebutCredit(): ?\DateTimeImmutable { return $this->dateDebutCredit; }
    public function setDateDebutCredit(?\DateTimeImmutable $dateDebutCredit): static { $this->dateDebutCredit = $dateDebutCredit; return $this; }
    public function getResteAFinancer(): ?string { return $this->resteAFinancer; }
    public function setResteAFinancer(?string $resteAFinancer): static { $this->resteAFinancer = $resteAFinancer; return $this; }
    public function getDureeMois(): ?int { return $this->dureeMois; }
    public function setDureeMois(?int $dureeMois): static { $this->dureeMois = $dureeMois; return $this; }
    public function getDernierMensualite(): ?string { return $this->dernierMensualite; }
    public function setDernierMensualite(?string $dernierMensualite): static { $this->dernierMensualite = $dernierMensualite; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
}
