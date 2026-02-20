<?php

namespace App\Entity;

use App\Repository\VisitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VisitRepository::class)]
#[ORM\Table(name: 'visits')]
#[ORM\Index(columns: ['id_patient'], name: 'idx_visit_patient')]
#[ORM\Index(columns: ['id_doctor'], name: 'idx_visit_doctor')]
#[ORM\Index(columns: ['id_box'], name: 'idx_visit_box')]
#[ORM\Index(columns: ['id_treatment'], name: 'idx_visit_treatment')]
#[ORM\Index(columns: ['date_visit'], name: 'idx_visit_date')]
class Visit
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_visit', type: Types::INTEGER)]
    private ?int $idVisit = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'visits')]
    #[ORM\JoinColumn(name: 'id_patient', referencedColumnName: 'patient_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Dentist::class, inversedBy: 'visits')]
    #[ORM\JoinColumn(name: 'id_doctor', referencedColumnName: 'doctor_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Dentist $dentist = null;

    #[ORM\ManyToOne(targetEntity: Box::class, inversedBy: 'visits')]
    #[ORM\JoinColumn(name: 'id_box', referencedColumnName: 'id_box', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Box $box = null;

    #[ORM\ManyToOne(targetEntity: Treatment::class, inversedBy: 'visits')]
    #[ORM\JoinColumn(name: 'id_treatment', referencedColumnName: 'id_treatment', nullable: true, onDelete: 'SET NULL')]
    private ?Treatment $treatment = null;

    #[ORM\Column(name: 'date_visit', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $dateVisit = null;

    #[ORM\Column(name: 'time_visit', type: Types::TIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $timeVisit = null;

    #[ORM\Column(name: 'reason_for_consultation', type: Types::TEXT, nullable: true)]
    private ?string $reasonForConsultation = null;

    #[ORM\Column(name: 'observations', type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    #[ORM\OneToMany(targetEntity: OdontogramDetail::class, mappedBy: 'visit', cascade: ['persist', 'remove'])]
    private Collection $odontogramDetails;

    public function __construct()
    {
        $this->odontogramDetails = new ArrayCollection();
    }

    public function getIdVisit(): ?int
    {
        return $this->idVisit;
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

    public function getDentist(): ?Dentist
    {
        return $this->dentist;
    }

    public function setDentist(?Dentist $dentist): static
    {
        $this->dentist = $dentist;
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

    public function getDateVisit(): ?\DateTimeInterface
    {
        return $this->dateVisit;
    }

    public function setDateVisit(\DateTimeInterface $dateVisit): static
    {
        $this->dateVisit = $dateVisit;
        return $this;
    }

    public function getTimeVisit(): ?\DateTimeInterface
    {
        return $this->timeVisit;
    }

    public function setTimeVisit(\DateTimeInterface $timeVisit): static
    {
        $this->timeVisit = $timeVisit;
        return $this;
    }

    public function getReasonForConsultation(): ?string
    {
        return $this->reasonForConsultation;
    }

    public function setReasonForConsultation(?string $reasonForConsultation): static
    {
        $this->reasonForConsultation = $reasonForConsultation;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;
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
            $odontogramDetail->setVisit($this);
        }
        return $this;
    }

    public function removeOdontogramDetail(OdontogramDetail $odontogramDetail): static
    {
        if ($this->odontogramDetails->removeElement($odontogramDetail)) {
            if ($odontogramDetail->getVisit() === $this) {
                $odontogramDetail->setVisit(null);
            }
        }
        return $this;
    }
}
