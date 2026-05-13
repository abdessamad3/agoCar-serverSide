<?php

namespace App\Entity;

use App\Repository\ContratRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContratRepository::class)]
class Contrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $creeAu = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $editAu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?client $client = null;


    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $creePar = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?client
    {
        return $this->client;
    }

    public function setClient(?client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getCreePar(): ?Utilisateur
    {
        return $this->creePar;
    }

    public function setCreePar(?Utilisateur $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    public function getReservation(): ?reservation
    {
        return $this->reservation;
    }

    public function setReservation(reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }
}
