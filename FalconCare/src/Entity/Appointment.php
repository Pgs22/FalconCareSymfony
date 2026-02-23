<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $visitDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $visitTime = null;

    #[ORM\Column(length: 255)]
    private ?string $consultationReason = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $observations = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Doctor $doctor = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Box $box = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Treatment $treatment = null;

    /**
     * @var Collection<int, Odontogram>
     */
    #[ORM\OneToMany(targetEntity: Odontogram::class, mappedBy: 'visit')]
    private Collection $odontograms;

    public function __construct()
    {
        $this->odontograms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitDate(): ?\DateTime
    {
        return $this->visitDate;
    }

    public function setVisitDate(\DateTime $visitDate): static
    {
        $this->visitDate = $visitDate;

        return $this;
    }

    public function getVisitTime(): ?\DateTime
    {
        return $this->visitTime;
    }

    public function setVisitTime(\DateTime $visitTime): static
    {
        $this->visitTime = $visitTime;

        return $this;
    }

    public function getConsultationReason(): ?string
    {
        return $this->consultationReason;
    }

    public function setConsultationReason(string $consultationReason): static
    {
        $this->consultationReason = $consultationReason;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(string $observations): static
    {
        $this->observations = $observations;

        return $this;
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

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getDoctor(): ?Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(?Doctor $doctor): static
    {
        $this->doctor = $doctor;

        return $this;
    }

    public function getBox(): ?Box
    {
        return $this->box;
    }

    public function setBox(?Box $box): static
    {
        $this->box = $box;

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
            $odontogram->setVisit($this);
        }

        return $this;
    }

    public function removeOdontogram(Odontogram $odontogram): static
    {
        if ($this->odontograms->removeElement($odontogram)) {
            // set the owning side to null (unless already changed)
            if ($odontogram->getVisit() === $this) {
                $odontogram->setVisit(null);
            }
        }

        return $this;
    }
}
