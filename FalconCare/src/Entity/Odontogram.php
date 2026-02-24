<?php

namespace App\Entity;

use App\Repository\OdontogramRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdontogramRepository::class)]
class Odontogram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $toothSurface = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Appointment $visit = null;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tooth $tooth = null;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pathology $pathology = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToothSurface(): ?string
    {
        return $this->toothSurface;
    }

    public function setToothSurface(?string $toothSurface): static
    {
        $this->toothSurface = $toothSurface;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getVisit(): ?Appointment
    {
        return $this->visit;
    }

    public function setVisit(?Appointment $visit): static
    {
        $this->visit = $visit;

        return $this;
    }

    public function getTooth(): ?Tooth
    {
        return $this->tooth;
    }

    public function setTooth(?Tooth $tooth): static
    {
        $this->tooth = $tooth;

        return $this;
    }

    public function getPathology(): ?Pathology
    {
        return $this->pathology;
    }

    public function setPathology(?Pathology $pathology): static
    {
        $this->pathology = $pathology;

        return $this;
    }
}
