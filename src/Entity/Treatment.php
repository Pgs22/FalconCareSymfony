<?php

namespace App\Entity;

use App\Repository\TreatmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TreatmentRepository::class)]
#[ORM\Table(name: 'treatment')]
#[ORM\Index(columns: ['treatment_name'], name: 'idx_treatment_name')]
class Treatment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_treatment', type: Types::INTEGER)]
    private ?int $idTreatment = null;

    #[ORM\Column(name: 'treatment_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $treatmentName = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'estimated_duration', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $estimatedDuration = null;

    #[ORM\OneToMany(targetEntity: Visit::class, mappedBy: 'treatment', cascade: ['persist', 'remove'])]
    private Collection $visits;

    public function __construct()
    {
        $this->visits = new ArrayCollection();
    }

    public function getIdTreatment(): ?int
    {
        return $this->idTreatment;
    }

    public function getTreatmentName(): ?string
    {
        return $this->treatmentName;
    }

    public function setTreatmentName(string $treatmentName): static
    {
        $this->treatmentName = $treatmentName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getEstimatedDuration(): ?string
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?string $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    /**
     * @return Collection<int, Visit>
     */
    public function getVisits(): Collection
    {
        return $this->visits;
    }

    public function addVisit(Visit $visit): static
    {
        if (!$this->visits->contains($visit)) {
            $this->visits->add($visit);
            $visit->setTreatment($this);
        }
        return $this;
    }

    public function removeVisit(Visit $visit): static
    {
        if ($this->visits->removeElement($visit)) {
            if ($visit->getTreatment() === $this) {
                $visit->setTreatment(null);
            }
        }
        return $this;
    }
}
