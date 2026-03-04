<?php

namespace App\Entity;

use App\Repository\OdontogramaDetailRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdontogramaDetailRepository::class)]
class OdontogramaDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $toothNumber = null;

    #[ORM\ManyToOne(inversedBy: 'odontogramaDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Odontogram $odontograma = null;

    #[ORM\ManyToOne(inversedBy: 'odontogramaDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pathology $pathology = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToothNumber(): ?int
    {
        return $this->toothNumber;
    }

    public function setToothNumber(int $toothNumber): static
    {
        $this->toothNumber = $toothNumber;

        return $this;
    }

    public function getOdontograma(): ?Odontogram
    {
        return $this->odontograma;
    }

    public function setOdontograma(?Odontogram $odontograma): static
    {
        $this->odontograma = $odontograma;

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
