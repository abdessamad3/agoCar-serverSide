<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\VoitureRepository;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: VoitureRepository::class)]
#[ORM\Index(columns: ['voiture_status'], name: 'idx_voiture_status')]
#[ORM\Index(columns: ['bureau_id'],      name: 'IDX_E9E2810F32516FE2')]
#[Vich\Uploadable]
class Voiture
{

    public function __construct()
    {
        $this->depenses = new ArrayCollection();
        $this->images   = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private ?string $marque = null;

    #[ORM\Column(length: 60)]
    private ?string $modele = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $version = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $immatriculation = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vin = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Vich\UploadableField(mapping: 'car_image', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column]
    private ?int $kilometrageActuel = null;

    #[ORM\Column(length: 30)]
    private ?string $typeCarburant = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $transmission = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(nullable: true)]
    private ?int $places = null;

    #[ORM\Column(nullable: true)]
    private ?int $portes = null;

    #[ORM\Column(nullable: true)]
    private ?int $puissanceCv = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column]
    private bool $climatisation = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixJour = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixSemaine = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixMois = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixAchat = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $caution = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateAchat = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateExpirationAssurance = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateExpirationVignette = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateExpirationVisite = null;

    #[ORM\Column(length: 30)]
    private ?string $voitureStatus = 'available';

    #[ORM\Column(length: 30)]
    private ?string $reservationStatus = 'confirmed';

    #[ORM\ManyToOne(targetEntity: Bureau::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bureau $bureau = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $creePar = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $editAu = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\OneToMany(mappedBy: 'voiture', targetEntity: Depense::class)]
    private Collection $depenses;

    #[ORM\OneToMany(mappedBy: 'voiture', targetEntity: VoitureImage::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    public function getId(): ?int { return $this->id; }
    public function getMarque(): ?string { return $this->marque; }
    public function setMarque(string $marque): static { $this->marque = $marque; return $this; }
    public function getModele(): ?string { return $this->modele; }
    public function setModele(string $modele): static { $this->modele = $modele; return $this; }
    public function getVersion(): ?string { return $this->version; }
    public function setVersion(?string $version): static { $this->version = $version; return $this; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }
    public function getImmatriculation(): ?string { return $this->immatriculation; }
    public function setImmatriculation(?string $immatriculation): static { $this->immatriculation = $immatriculation; return $this; }
    public function getVin(): ?string { return $this->vin; }
    public function setVin(?string $vin): static { $this->vin = $vin; return $this; }
    public function getKilometrageActuel(): ?int { return $this->kilometrageActuel; }
    public function setKilometrageActuel(int $kilometrageActuel): static { $this->kilometrageActuel = $kilometrageActuel; return $this; }
    public function getTypeCarburant(): ?string { return $this->typeCarburant; }
    public function setTypeCarburant(string $typeCarburant): static { $this->typeCarburant = $typeCarburant; return $this; }
    public function getTransmission(): ?string { return $this->transmission; }
    public function setTransmission(?string $transmission): static { $this->transmission = $transmission; return $this; }
    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): static { $this->couleur = $couleur; return $this; }
    public function getPlaces(): ?int { return $this->places; }
    public function setPlaces(?int $places): static { $this->places = $places; return $this; }
    public function getPortes(): ?int { return $this->portes; }
    public function setPortes(?int $portes): static { $this->portes = $portes; return $this; }
    public function getPuissanceCv(): ?int { return $this->puissanceCv; }
    public function setPuissanceCv(?int $puissanceCv): static { $this->puissanceCv = $puissanceCv; return $this; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): static { $this->categorie = $categorie; return $this; }
    public function isClimatisation(): bool { return $this->climatisation; }
    public function setClimatisation(bool $climatisation): static { $this->climatisation = $climatisation; return $this; }
    public function getPrixJour(): ?string { return $this->prixJour; }
    public function setPrixJour(string $prixJour): static { $this->prixJour = $prixJour; return $this; }
    public function getPrixSemaine(): ?string { return $this->prixSemaine; }
    public function setPrixSemaine(?string $prixSemaine): static { $this->prixSemaine = $prixSemaine; return $this; }
    public function getPrixMois(): ?string { return $this->prixMois; }
    public function setPrixMois(?string $prixMois): static { $this->prixMois = $prixMois; return $this; }
    public function getPrixAchat(): ?string { return $this->prixAchat; }
    public function setPrixAchat(?string $prixAchat): static { $this->prixAchat = $prixAchat; return $this; }
    public function getCaution(): ?string { return $this->caution; }
    public function setCaution(?string $caution): static { $this->caution = $caution; return $this; }
    public function getDateAchat(): ?\DateTimeInterface { return $this->dateAchat; }
    public function setDateAchat(?\DateTimeInterface $dateAchat): static { $this->dateAchat = $dateAchat; return $this; }
    public function getDateExpirationAssurance(): ?\DateTimeInterface { return $this->dateExpirationAssurance; }
    public function setDateExpirationAssurance(?\DateTimeInterface $d): static { $this->dateExpirationAssurance = $d; return $this; }
    public function getDateExpirationVignette(): ?\DateTimeInterface { return $this->dateExpirationVignette; }
    public function setDateExpirationVignette(?\DateTimeInterface $d): static { $this->dateExpirationVignette = $d; return $this; }
    public function getDateExpirationVisite(): ?\DateTimeInterface { return $this->dateExpirationVisite; }
    public function setDateExpirationVisite(?\DateTimeInterface $d): static { $this->dateExpirationVisite = $d; return $this; }
    public function getVoitureStatus(): ?string { return $this->voitureStatus; }
    public function setVoitureStatus(string $voitureStatus): static { $this->voitureStatus = $voitureStatus; return $this; }
    public function getReservationStatus(): ?string { return $this->reservationStatus; }
    public function setReservationStatus(string $reservationStatus): static { $this->reservationStatus = $reservationStatus; return $this; }
    public function getBureau(): ?Bureau { return $this->bureau; }
    public function setBureau(?Bureau $bureau): static { $this->bureau = $bureau; return $this; }
    public function getCreePar(): ?Utilisateur { return $this->creePar; }
    public function setCreePar(?Utilisateur $creePar): static { $this->creePar = $creePar; return $this; }
    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }
    public function setCreeAu(?\DateTimeImmutable $creeAu): static { $this->creeAu = $creeAu; return $this; }
    public function getEditAu(): ?\DateTimeImmutable { return $this->editAu; }
    public function setEditAu(?\DateTimeImmutable $editAu): static { $this->editAu = $editAu; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
    public function getDepenses(): Collection { return $this->depenses; }
    public function getImages(): Collection   { return $this->images; }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->editAu = new \DateTimeImmutable();
        }
    }
    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

     public function getImageName(): ?string
    {
        return $this->imageName;
    }

    // HELPER: Get the full public path for Angular
    public function getImagePath(): ?string
    {
        return $this->imageName ? '/uploads/cars/' . $this->imageName : null;
    }
}