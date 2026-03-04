<?php

namespace App\Entity;

use App\Repository\OdontogramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OdontogramRepository::class)]
class Odontogram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Appointment $visit = null;

    /**
     * @var Collection<int, OdontogramaDetail>
     */
    #[ORM\OneToMany(targetEntity: OdontogramaDetail::class, mappedBy: 'odontograma')]
    private Collection $odontogramaDetails;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Treatment $treatment = null;

    public function __construct()
    {
        $this->odontogramaDetails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
            $odontogramaDetail->setOdontograma($this);
        }

        return $this;
    }

    public function removeOdontogramaDetail(OdontogramaDetail $odontogramaDetail): static
    {
        if ($this->odontogramaDetails->removeElement($odontogramaDetail)) {
            // set the owning side to null (unless already changed)
            if ($odontogramaDetail->getOdontograma() === $this) {
                $odontogramaDetail->setOdontograma(null);
            }
        }

        return $this;
    }

    public function getTreatment(): ?Treatment
    {
        return $this->treatment;
    }

    public function setTreatment(?Treatment $treatment): static
    {
        $this->treatment = $treatment;

        return $this;
    }
}
