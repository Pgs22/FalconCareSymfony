<?php

namespace App\Entity;

use App\Repository\PathologyTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PathologyTypeRepository::class)]
class PathologyType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $default_duration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDefaultDuration(): ?int
    {
        return $this->default_duration;
    }

    public function setDefaultDuration(int $default_duration): static
    {
        $this->default_duration = $default_duration;

        return $this;
    }
}
