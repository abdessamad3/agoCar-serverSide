<?php

namespace App\Entity;

use App\Repository\PaymentAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentAttachmentRepository::class)]
#[ORM\Table(name: 'payment_attachment')]
class PaymentAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleCreditPayment::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleCreditPayment $payment = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fileType = null; // pdf, image, etc.

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $uploadedAt = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPayment(): ?VehicleCreditPayment { return $this->payment; }
    public function setPayment(?VehicleCreditPayment $v): static { $this->payment = $v; return $this; }
    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(string $v): static { $this->fileName = $v; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $v): static { $this->filePath = $v; return $this; }
    public function getFileType(): ?string { return $this->fileType; }
    public function setFileType(?string $v): static { $this->fileType = $v; return $this; }
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
}
