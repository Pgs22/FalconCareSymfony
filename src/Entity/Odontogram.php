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
     * @var Collection<int, OdontogramDetail>
     */
    #[ORM\OneToMany(targetEntity: OdontogramDetail::class, mappedBy: 'odontogram')]
    private Collection $odontogramDetails;

    #[ORM\ManyToOne(inversedBy: 'odontograms')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Treatment $treatment = null;

    public function __construct()
    {
        $this->odontogramDetails = new ArrayCollection();
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
     * @return Collection<int, OdontogramDetail>
     */
    public function getOdontogramDetails(): Collection
    {
        return $this->odontogramDetails;
    }

    public function addOdontogramDetail(OdontogramDetail $odontogramDetail): static
    {
        if (!$this->odontogramDetails->contains($odontogramDetail)) {
            $this->odontogramDetails->add($odontogramDetail);
            $odontogramDetail->setOdontogram($this);
        }

        return $this;
    }

    public function removeOdontogramDetail(OdontogramDetail $odontogramDetail): static
    {
        if ($this->odontogramDetails->removeElement($odontogramDetail)) {

            if ($odontogramDetail->getOdontogram() === $this) {
                $odontogramDetail->setOdontogram(null);
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
