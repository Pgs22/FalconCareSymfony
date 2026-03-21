<?php

namespace App\Entity;

use App\Repository\ToothFaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ToothFaceRepository::class)]
class ToothFace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $faceName = null;

    #[ORM\ManyToOne(inversedBy: 'toothFaces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OdontogramDetail $odontogramDetail = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFaceName(): ?string
    {
        return $this->faceName;
    }

    public function setFaceName(string $faceName): static
    {
        $this->faceName = $faceName;

        return $this;
    }

    public function getOdontogramDetail(): ?OdontogramDetail
    {
        return $this->odontogramDetail;
    }

    public function setOdontogramDetail(?OdontogramDetail $odontogramDetail): static
    {
        $this->odontogramDetail = $odontogramDetail;

        return $this;
    }
}
