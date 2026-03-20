<?php

namespace App\Entity;

use App\Repository\TreatmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TreatmentRepository::class)]
class Treatment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $treatmentName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedDuration = null;

    /**
     * @var Collection<int, Appointment>
     */
    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'treatment')]
    private Collection $appointments;

    #[ORM\Column(nullable: true)]
    private ?int $lastOdontogramId = null;

    /**
     * @var Collection<int, Pathology>
     */
    #[ORM\OneToMany(targetEntity: Pathology::class, mappedBy: 'treatment')]
    private Collection $pathologies;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $status = null;

    /**
     * @var Collection<int, Odontogram>
     */
    #[ORM\OneToMany(targetEntity: Odontogram::class, mappedBy: 'treatment')]
    private Collection $odontograms;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $schedulingNotes = null;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
        $this->pathologies = new ArrayCollection();
        $this->odontograms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(int $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;

        return $this;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): static
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setTreatment($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            // set the owning side to null (unless already changed)
            if ($appointment->getTreatment() === $this) {
                $appointment->setTreatment(null);
            }
        }

        return $this;
    }

    public function getLastOdontogramId(): ?int
    {
        return $this->lastOdontogramId;
    }

    public function setLastOdontogramId(?int $lastOdontogramId): static
    {
        $this->lastOdontogramId = $lastOdontogramId;

        return $this;
    }

    /**
     * @return Collection<int, Pathology>
     */
    public function getPathologies(): Collection
    {
        return $this->pathologies;
    }

    public function addPathology(Pathology $pathology): self
    {
        if (!$this->pathologies->contains($pathology)) {
                $this->pathologies->add($pathology);
                $pathology->setTreatment($this); 
        }
        return $this;
    }

    public function removePathology(Pathology $pathology): self
    {
        if ($this->pathologies->removeElement($pathology)) {
            if ($pathology->getTreatment() === $this) {
                $pathology->setTreatment(null);
            }
        }
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

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
            $odontogram->setTreatment($this);
        }

        return $this;
    }

    public function removeOdontogram(Odontogram $odontogram): static
    {
        if ($this->odontograms->removeElement($odontogram)) {
            // set the owning side to null (unless already changed)
            if ($odontogram->getTreatment() === $this) {
                $odontogram->setTreatment(null);
            }
        }

        return $this;
    }

    public function getSchedulingNotes(): ?string
    {
        return $this->schedulingNotes;
    }

    public function setSchedulingNotes(?string $schedulingNotes): static
    {
        $this->schedulingNotes = $schedulingNotes;

        return $this;
    }

    
}
