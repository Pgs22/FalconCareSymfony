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
}
