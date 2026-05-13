<?php

namespace App\Entity;

use App\Repository\AssuranceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssuranceRepository::class)]
class Assurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $datePaiment = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?int $numeroMoi = null;

    #[ORM\Column]
    private ?\DateTime $creeAu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $editAu = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Depense $depense = null;

    /**
     * @var Collection<int, Utilisateur>
     */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $creePar;

    public function __construct()
    {
        $this->creePar = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getDatePaiment(): ?\DateTime
    {
        return $this->datePaiment;
    }

    public function setDatePaiment(\DateTime $datePaiment): static
    {
        $this->datePaiment = $datePaiment;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNumeroMoi(): ?int
    {
        return $this->numeroMoi;
    }

    public function setNumeroMoi(int $numeroMoi): static
    {
        $this->numeroMoi = $numeroMoi;

        return $this;
    }

    public function getCreeAu(): ?\DateTime
    {
        return $this->creeAu;
    }

    public function setCreeAu(\DateTime $creeAu): static
    {
        $this->creeAu = $creeAu;

        return $this;
    }

    public function getEditAu(): ?\DateTime
    {
        return $this->editAu;
    }

    public function setEditAu(?\DateTime $editAu): static
    {
        $this->editAu = $editAu;

        return $this;
    }

    public function getDepense(): ?Depense
    {
        return $this->depense;
    }

    public function setDepense(Depense $depense): static
    {
        $this->depense = $depense;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getCreePar(): Collection
    {
        return $this->creePar;
    }

    public function addCreePar(Utilisateur $creePar): static
    {
        if (!$this->creePar->contains($creePar)) {
            $this->creePar->add($creePar);
        }

        return $this;
    }

    public function removeCreePar(Utilisateur $creePar): static
    {
        $this->creePar->removeElement($creePar);

        return $this;
    }
}
