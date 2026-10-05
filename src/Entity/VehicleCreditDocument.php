<?php

namespace App\Entity;

use App\Repository\VehicleCreditDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleCreditDocumentRepository::class)]
#[ORM\Table(name: 'vehicle_credit_document')]
class VehicleCreditDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleCredit::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleCredit $vehicleCredit = null;

    #[ORM\Column(length: 50)]
    private ?string $documentType = null; // financing_contract, bank_approval, amortization_schedule, insurance, settlement_certificate, other

    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $uploadedAt = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVehicleCredit(): ?VehicleCredit { return $this->vehicleCredit; }
    public function setVehicleCredit(?VehicleCredit $v): static { $this->vehicleCredit = $v; return $this; }
    public function getDocumentType(): ?string { return $this->documentType; }
    public function setDocumentType(string $v): static { $this->documentType = $v; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $v): static { $this->filePath = $v; return $this; }
    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $v): static { $this->fileName = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
}
