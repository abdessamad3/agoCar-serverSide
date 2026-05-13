<?php

namespace App\Entity;

use App\Repository\VidangeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VidangeRepository::class)]
class Vidange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?int $kilometrageSuivant = null;

    #[ORM\Column]
    private ?bool $filtreAir = null;

    #[ORM\Column]
    private ?bool $filtreHuile = null;

    #[ORM\Column]
    private ?bool $filtreCarburant = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixTotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixPayee = null;

    #[ORM\Column]
    private ?\DateTime $creeAu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $editAu = null;

    /**
     * @var Collection<int, depense>
     */
    #[ORM\OneToMany(targetEntity: depense::class, mappedBy: 'vidange')]
    private Collection $depense;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?utilisateur $creePar = null;

    public function __construct()
    {
        $this->depense = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getKilometrageSuivant(): ?int
    {
        return $this->kilometrageSuivant;
    }

    public function setKilometrageSuivant(int $kilometrageSuivant): static
    {
        $this->kilometrageSuivant = $kilometrageSuivant;

        return $this;
    }

    public function isFiltreAir(): ?bool
    {
        return $this->filtreAir;
    }

    public function setFiltreAir(bool $filtreAir): static
    {
        $this->filtreAir = $filtreAir;

        return $this;
    }

    public function isFiltreHuile(): ?bool
    {
        return $this->filtreHuile;
    }

    public function setFiltreHuile(bool $filtreHuile): static
    {
        $this->filtreHuile = $filtreHuile;

        return $this;
    }

    public function isFiltreCarburant(): ?bool
    {
        return $this->filtreCarburant;
    }

    public function setFiltreCarburant(bool $filtreCarburant): static
    {
        $this->filtreCarburant = $filtreCarburant;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(string $prixTotal): static
    {
        $this->prixTotal = $prixTotal;

        return $this;
    }

    public function getPrixPayee(): ?string
    {
        return $this->prixPayee;
    }

    public function setPrixPayee(string $prixPayee): static
    {
        $this->prixPayee = $prixPayee;

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

    /**
     * @return Collection<int, depense>
     */
    public function getDepense(): Collection
    {
        return $this->depense;
    }

    public function addDepense(depense $depense): static
    {
        if (!$this->depense->contains($depense)) {
            $this->depense->add($depense);
            $depense->setVidange($this);
        }

        return $this;
    }

    public function removeDepense(depense $depense): static
    {
        if ($this->depense->removeElement($depense)) {
            // set the owning side to null (unless already changed)
            if ($depense->getVidange() === $this) {
                $depense->setVidange(null);
            }
        }

        return $this;
    }

    public function getCreePar(): ?utilisateur
    {
        return $this->creePar;
    }

    public function setCreePar(?utilisateur $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }
}
