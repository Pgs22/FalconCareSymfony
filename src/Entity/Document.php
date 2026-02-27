<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['document:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $captureDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Patient $patient = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCaptureDate(): ?\DateTimeImmutable
    {
        return $this->captureDate;
    }

    public function setCaptureDate(\DateTimeImmutable $captureDate): static
    {
        $this->captureDate = $captureDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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
}
