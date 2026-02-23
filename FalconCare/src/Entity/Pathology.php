<?php

namespace App\Entity;

use App\Repository\PathologyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PathologyRepository::class)]
class Pathology
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    private ?string $protocolColor = null;

    /**
     * @var Collection<int, Odontogram>
     */
    #[ORM\OneToMany(targetEntity: Odontogram::class, mappedBy: 'pathology')]
    private Collection $odontograms;

    public function __construct()
    {
        $this->odontograms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProtocolColor(): ?string
    {
        return $this->protocolColor;
    }

    public function setProtocolColor(string $protocolColor): static
    {
        $this->protocolColor = $protocolColor;

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
            $odontogram->setPathology($this);
        }

        return $this;
    }

    public function removeOdontogram(Odontogram $odontogram): static
    {
        if ($this->odontograms->removeElement($odontogram)) {
            // set the owning side to null (unless already changed)
            if ($odontogram->getPathology() === $this) {
                $odontogram->setPathology(null);
            }
        }

        return $this;
    }
}
