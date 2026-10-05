<?php

namespace App\Entity;

use App\Repository\ContractExtensionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractExtensionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ContractExtension
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contrat::class, inversedBy: 'extensions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Contrat $contrat = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeImmutable $dateFrom = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeImmutable $dateTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeAu = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->creeAu = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getContrat(): ?Contrat { return $this->contrat; }
    public function setContrat(?Contrat $c): static { $this->contrat = $c; return $this; }

    public function getDateFrom(): ?\DateTimeImmutable { return $this->dateFrom; }
    public function setDateFrom(?\DateTimeImmutable $v): static { $this->dateFrom = $v; return $this; }

    public function getDateTo(): ?\DateTimeImmutable { return $this->dateTo; }
    public function setDateTo(?\DateTimeImmutable $v): static { $this->dateTo = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreeAu(): ?\DateTimeImmutable { return $this->creeAu; }

    public function getNbJours(): int
    {
        if ($this->dateFrom && $this->dateTo) {
            return (int) $this->dateFrom->diff($this->dateTo)->days;
        }
        return 0;
    }
}
