<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $cin = null;

    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $passeport = null;

    #[ORM\Column(length: 50, nullable: true, unique: true)]
    private ?string $permisConduite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nationalite = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $adresseMaroc = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseEtranger = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneEtranger = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $permisDelivreLe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $permisDelivreA = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $passeportDelivreLe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passeportDelivreA = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $cinDelivreLe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cinDelivreA = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $cinExpiration = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $passeportExpiration = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $permisExpiration = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    /** Bureau of the user who created this client — set once at creation, doesn't follow the creator if reassigned later. */
    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    private ?Bureau $bureau = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getCin(): ?string { return $this->cin; }
    public function setCin(?string $cin): static { $this->cin = $cin; return $this; }
    public function getPasseport(): ?string { return $this->passeport; }
    public function setPasseport(?string $passeport): static { $this->passeport = $passeport; return $this; }
    public function getPermisConduite(): ?string { return $this->permisConduite; }
    public function setPermisConduite(?string $permisConduite): static { $this->permisConduite = $permisConduite; return $this; }
    public function getNationalite(): ?string { return $this->nationalite; }
    public function setNationalite(?string $nationalite): static { $this->nationalite = $nationalite; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getDateNaissance(): ?\DateTimeImmutable { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static { $this->dateNaissance = $dateNaissance; return $this; }
    public function getAdresseMaroc(): string { return $this->adresseMaroc; }
    public function setAdresseMaroc(string $adresseMaroc): static { $this->adresseMaroc = $adresseMaroc; return $this; }
    public function getAdresseEtranger(): ?string { return $this->adresseEtranger; }
    public function setAdresseEtranger(?string $adresseEtranger): static { $this->adresseEtranger = $adresseEtranger; return $this; }
    public function getLieuNaissance(): ?string { return $this->lieuNaissance; }
    public function setLieuNaissance(?string $v): static { $this->lieuNaissance = $v; return $this; }
    public function getTelephoneEtranger(): ?string { return $this->telephoneEtranger; }
    public function setTelephoneEtranger(?string $v): static { $this->telephoneEtranger = $v; return $this; }
    public function getPermisDelivreLe(): ?\DateTimeImmutable { return $this->permisDelivreLe; }
    public function setPermisDelivreLe(?\DateTimeImmutable $v): static { $this->permisDelivreLe = $v; return $this; }
    public function getPermisDelivreA(): ?string { return $this->permisDelivreA; }
    public function setPermisDelivreA(?string $v): static { $this->permisDelivreA = $v; return $this; }
    public function getPasseportDelivreLe(): ?\DateTimeImmutable { return $this->passeportDelivreLe; }
    public function setPasseportDelivreLe(?\DateTimeImmutable $v): static { $this->passeportDelivreLe = $v; return $this; }
    public function getPasseportDelivreA(): ?string { return $this->passeportDelivreA; }
    public function setPasseportDelivreA(?string $v): static { $this->passeportDelivreA = $v; return $this; }
    public function getCinDelivreLe(): ?\DateTimeImmutable { return $this->cinDelivreLe; }
    public function setCinDelivreLe(?\DateTimeImmutable $v): static { $this->cinDelivreLe = $v; return $this; }
    public function getCinDelivreA(): ?string { return $this->cinDelivreA; }
    public function setCinDelivreA(?string $v): static { $this->cinDelivreA = $v; return $this; }
    public function getCinExpiration(): ?\DateTimeImmutable { return $this->cinExpiration; }
    public function setCinExpiration(?\DateTimeImmutable $v): static { $this->cinExpiration = $v; return $this; }
    public function getPasseportExpiration(): ?\DateTimeImmutable { return $this->passeportExpiration; }
    public function setPasseportExpiration(?\DateTimeImmutable $v): static { $this->passeportExpiration = $v; return $this; }
    public function getPermisExpiration(): ?\DateTimeImmutable { return $this->permisExpiration; }
    public function setPermisExpiration(?\DateTimeImmutable $v): static { $this->permisExpiration = $v; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
}