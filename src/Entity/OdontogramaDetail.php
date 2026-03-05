<?php

namespace App\Entity;

use App\Repository\OdontogramaDetailRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, ToothFace>
     */
    #[ORM\OneToMany(targetEntity: ToothFace::class, mappedBy: 'odontogramaDetail')]
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
            $toothFace->setOdontogramaDetail($this);
        }

        return $this;
    }

    public function removeToothFace(ToothFace $toothFace): static
    {
        if ($this->toothFaces->removeElement($toothFace)) {
            // set the owning side to null (unless already changed)
            if ($toothFace->getOdontogramaDetail() === $this) {
                $toothFace->setOdontogramaDetail(null);
            }
        }

        return $this;
    }
}
