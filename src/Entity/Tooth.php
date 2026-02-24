<?php

namespace App\Entity;

use App\Repository\ToothRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ToothRepository::class)]
class Tooth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $toothId = null;

    #[ORM\Column(length: 100)]
    private ?string $description = null;

    /**
     * @var Collection<int, Odontogram>
     */
    #[ORM\OneToMany(targetEntity: Odontogram::class, mappedBy: 'tooth')]
    private Collection $odontograms;

    public function __construct()
    {
        $this->odontograms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToothId(): ?int
    {
        return $this->toothId;
    }

    public function setToothId(int $toothId): static
    {
        $this->toothId = $toothId;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Odontogram>
     */
    public function getOdontograms(): Collection
    {
        return $this->odontograms;
    }

    public function addOdontogram(Odontogram $odontogram): static
    {
        if (!$this->odontograms->contains($odontogram)) {
            $this->odontograms->add($odontogram);
            $odontogram->setTooth($this);
        }

        return $this;
    }

    public function removeOdontogram(Odontogram $odontogram): static
    {
        if ($this->odontograms->removeElement($odontogram)) {
            // set the owning side to null (unless already changed)
            if ($odontogram->getTooth() === $this) {
                $odontogram->setTooth(null);
            }
        }

        return $this;
    }
}
