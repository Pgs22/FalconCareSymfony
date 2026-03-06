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
     * @var Collection<int, Treatment>
     */
    #[ORM\ManyToMany(targetEntity: Treatment::class, mappedBy: 'pathologies')]
    private Collection $treatments;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $visualType = null;

    /**
     * @var Collection<int, OdontogramaDetail>
     */
    #[ORM\OneToMany(targetEntity: OdontogramaDetail::class, mappedBy: 'pathology')]
    private Collection $odontogramaDetails;

    public function __construct()
    {
        $this->treatments = new ArrayCollection();
        $this->odontogramaDetails = new ArrayCollection();
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
     * @return Collection<int, Treatment>
     */
    public function getTreatments(): Collection
    {
        return $this->treatments;
    }

    public function addTreatment(Treatment $treatment): static
    {
        if (!$this->treatments->contains($treatment)) {
            $this->treatments->add($treatment);
            $treatment->addPathology($this);
        }

        return $this;
    }

    public function removeTreatment(Treatment $treatment): static
    {
        if ($this->treatments->removeElement($treatment)) {
            $treatment->removePathology($this);
        }

        return $this;
    }

    public function getVisualType(): ?string
    {
        return $this->visualType;
    }

    public function setVisualType(?string $visualType): static
    {
        $this->visualType = $visualType;

        return $this;
    }

    /**
     * @return Collection<int, OdontogramaDetail>
     */
    public function getOdontogramaDetails(): Collection
    {
        return $this->odontogramaDetails;
    }

    public function addOdontogramaDetail(OdontogramaDetail $odontogramaDetail): static
    {
        if (!$this->odontogramaDetails->contains($odontogramaDetail)) {
            $this->odontogramaDetails->add($odontogramaDetail);
            $odontogramaDetail->setPathology($this);
        }

        return $this;
    }

    public function removeOdontogramaDetail(OdontogramaDetail $odontogramaDetail): static
    {
        if ($this->odontogramaDetails->removeElement($odontogramaDetail)) {
            // set the owning side to null (unless already changed)
            if ($odontogramaDetail->getPathology() === $this) {
                $odontogramaDetail->setPathology(null);
            }
        }

        return $this;
    }
}
