<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Documents table (English spec).
 * Image_ID (PK), Patient_ID (FK), Type (X-ray, Scan), File_Path (URL/Path), Capture_Date.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\Index(columns: ['patient_id'], name: 'idx_document_patient')]
#[ORM\Index(columns: ['capture_date'], name: 'idx_document_capture_date')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'image_id', type: Types::INTEGER)]
    private ?int $imageId = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'patient_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Patient $patient = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $type = null;

    #[ORM\Column(name: 'file_path', type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private ?string $filePath = null;

    #[ORM\Column(name: 'capture_date', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $captureDate = null;

    public function __construct()
    {
        $this->captureDate = new \DateTime();
    }

    public function getImageId(): ?int
    {
        return $this->imageId;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getCaptureDate(): ?\DateTimeInterface
    {
        return $this->captureDate;
    }

    public function setCaptureDate(\DateTimeInterface $captureDate): static
    {
        $this->captureDate = $captureDate;
        return $this;
    }
}
