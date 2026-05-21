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
class Voiture
{

    public function __construct()
    {
        $this->depenses = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private ?string $marque = null;

    #[ORM\Column(length: 60)]
    private ?string $modele = null;

    #[ORM\Column]
    private ?int $annee = null;

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
    private ?string $couleur = null;

    #[ORM\Column]
    private bool $climatisation = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixJour = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixAchat = null;

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

    #[ORM\OneToMany(mappedBy: 'voiture', targetEntity: Depense::class)]
    private Collection $depenses;   

    public function getId(): ?int { return $this->id; }
    public function getMarque(): ?string { return $this->marque; }
    public function setMarque(string $marque): static { $this->marque = $marque; return $this; }
    public function getModele(): ?string { return $this->modele; }
    public function setModele(string $modele): static { $this->modele = $modele; return $this; }
    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }
    public function getKilometrageActuel(): ?int { return $this->kilometrageActuel; }
    public function setKilometrageActuel(int $kilometrageActuel): static { $this->kilometrageActuel = $kilometrageActuel; return $this; }
    public function getTypeCarburant(): ?string { return $this->typeCarburant; }
    public function setTypeCarburant(string $typeCarburant): static { $this->typeCarburant = $typeCarburant; return $this; }
    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): static { $this->couleur = $couleur; return $this; }
    public function isClimatisation(): bool { return $this->climatisation; }
    public function setClimatisation(bool $climatisation): static { $this->climatisation = $climatisation; return $this; }
    public function getPrixJour(): ?string { return $this->prixJour; }
    public function setPrixJour(string $prixJour): static { $this->prixJour = $prixJour; return $this; }
    public function getPrixAchat(): ?string { return $this->prixAchat; }
    public function setPrixAchat(?string $prixAchat): static { $this->prixAchat = $prixAchat; return $this; }
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
    public function getDepenses(): Collection{ return $this->depenses;}

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
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