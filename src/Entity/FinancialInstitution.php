<?php

namespace App\Entity;

use App\Repository\FinancialInstitutionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialInstitutionRepository::class)]
#[ORM\Table(name: 'financial_institution')]
class FinancialInstitution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // bank, leasing, credit, manufacturer, other

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // active, inactive

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: VehicleCredit::class, mappedBy: 'financialInstitution')]
    private Collection $vehicleCredits;

    public function __construct()
    {
        $this->vehicleCredits = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getContactPerson(): ?string { return $this->contactPerson; }
    public function setContactPerson(?string $v): static { $this->contactPerson = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): static { $this->address = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
    public function getVehicleCredits(): Collection { return $this->vehicleCredits; }
}
