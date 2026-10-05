<?php

namespace App\Entity;

use App\Repository\ParametresSocieteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametresSocieteRepository::class)]
class ParametresSociete
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $raisonSociale = null;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $telephones = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $rc = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ice = null;

    #[ORM\Column(length: 100, nullable: true, name: 'if_fiscal')]
    private ?string $ifFiscal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cnss = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $whatsapp = null;

    public function getId(): int { return $this->id; }
    public function setId(int $id): static { $this->id = $id; return $this; }

    public function getRaisonSociale(): ?string { return $this->raisonSociale; }
    public function setRaisonSociale(?string $raisonSociale): static { $this->raisonSociale = $raisonSociale; return $this; }

    public function getTelephones(): array { return $this->telephones; }
    public function setTelephones(array $telephones): static { $this->telephones = $telephones; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): static { $this->adresse = $adresse; return $this; }

    public function getRc(): ?string { return $this->rc; }
    public function setRc(?string $rc): static { $this->rc = $rc; return $this; }

    public function getIce(): ?string { return $this->ice; }
    public function setIce(?string $ice): static { $this->ice = $ice; return $this; }

    public function getIfFiscal(): ?string { return $this->ifFiscal; }
    public function setIfFiscal(?string $ifFiscal): static { $this->ifFiscal = $ifFiscal; return $this; }

    public function getCnss(): ?string { return $this->cnss; }
    public function setCnss(?string $cnss): static { $this->cnss = $cnss; return $this; }

    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $logoPath): static { $this->logoPath = $logoPath; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): static { $this->website = $website; return $this; }

    public function getWhatsapp(): ?string { return $this->whatsapp; }
    public function setWhatsapp(?string $whatsapp): static { $this->whatsapp = $whatsapp; return $this; }
}
