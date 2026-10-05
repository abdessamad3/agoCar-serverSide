<?php

namespace App\Entity;

use App\Repository\ConditionsContratRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConditionsContratRepository::class)]
class ConditionsContrat
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $texteFrancais = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $texteArabe = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): int { return $this->id; }
    public function setId(int $id): static { $this->id = $id; return $this; }

    public function getTexteFrancais(): ?string { return $this->texteFrancais; }
    public function setTexteFrancais(?string $texteFrancais): static { $this->texteFrancais = $texteFrancais; return $this; }

    public function getTexteArabe(): ?string { return $this->texteArabe; }
    public function setTexteArabe(?string $texteArabe): static { $this->texteArabe = $texteArabe; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
