<?php

namespace App\Entity;

use App\Repository\ClientDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ClientDocumentRepository::class)]
#[Vich\Uploadable]
class ClientDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(length: 30)]
    private string $documentType = 'autre';

    #[Vich\UploadableField(mapping: 'client_document', fileNameProperty: 'documentName')]
    private ?File $documentFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getDocumentType(): string { return $this->documentType; }
    public function setDocumentType(string $documentType): static { $this->documentType = $documentType; return $this; }

    public function setDocumentFile(?File $file = null): void
    {
        $this->documentFile = $file;
        if ($file) {
            $this->uploadedAt = new \DateTimeImmutable();
        }
    }
    public function getDocumentFile(): ?File { return $this->documentFile; }

    public function getDocumentName(): ?string { return $this->documentName; }
    public function setDocumentName(?string $documentName): static { $this->documentName = $documentName; return $this; }

    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(?string $originalName): static { $this->originalName = $originalName; return $this; }

    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): static { $this->uploadedAt = $uploadedAt; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
}
