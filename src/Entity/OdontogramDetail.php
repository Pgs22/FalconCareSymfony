<?php

namespace App\Entity;

use App\Repository\OdontogramDetailRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdontogramDetailRepository::class)]
class OdontogramDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $toothNumber = null;

    #[ORM\ManyToOne(inversedBy: 'odontogramDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Odontogram $odontogram = null;

    #[ORM\ManyToOne(inversedBy: 'odontogramDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pathology $pathology = null;

    /**
     * @var Collection<int, ToothFace>
     */
    #[ORM\OneToMany(targetEntity: ToothFace::class, mappedBy: 'odontogramDetail')]
    private Collection $toothFaces;

    public function __construct()
    {
        $this->toothFaces = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToothNumber(): ?int
    {
        return $this->toothNumber;
    }

    public function setToothNumber(?int $toothNumber): static
    {
        $this->toothNumber = $toothNumber;

        return $this;
    }

    public function getOdontogram(): ?Odontogram
    {
        return $this->odontogram;
    }

    public function setOdontogram(?Odontogram $odontogram): static
    {
        $this->odontogram = $odontogram;

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

    /**
     * @return Collection<int, ToothFace>
     */
    public function getToothFaces(): Collection
    {
        return $this->toothFaces;
    }

    public function addToothFace(ToothFace $toothFace): static
    {
        if (!$this->toothFaces->contains($toothFace)) {
            $this->toothFaces->add($toothFace);
            $toothFace->setOdontogramDetail($this);
        }

        return $this;
    }

    public function removeToothFace(ToothFace $toothFace): static
    {
        if ($this->toothFaces->removeElement($toothFace)) {
            if ($toothFace->getOdontogramDetail() === $this) {
                $toothFace->setOdontogramDetail(null);
            }
        }

        return $this;
    }
}
