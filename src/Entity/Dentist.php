<?php

namespace App\Entity;

use App\Repository\DentistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dentists table (English spec).
 * Doctor_ID (PK), First_Name, Last_Name, Specialty,
 * Assigned_Day_of_Week, Phone, Email.
 */
#[ORM\Entity(repositoryClass: DentistRepository::class)]
#[ORM\Table(name: 'dentists')]
#[ORM\Index(columns: ['email'], name: 'idx_dentist_email')]
class Dentist
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'doctor_id', type: Types::INTEGER)]
    private ?int $doctorId = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'specialty', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $specialty = null;

    #[ORM\Column(name: 'assigned_day_of_week', type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $assignedDayOfWeek = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\OneToMany(targetEntity: Visit::class, mappedBy: 'dentist', cascade: ['persist', 'remove'])]
    private Collection $visits;

    public function __construct()
    {
        $this->visits = new ArrayCollection();
    }

    public function getDoctorId(): ?int
    {
        return $this->doctorId;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(string $specialty): static
    {
        $this->specialty = $specialty;
        return $this;
    }

    public function getAssignedDayOfWeek(): ?string
    {
        return $this->assignedDayOfWeek;
    }

    public function setAssignedDayOfWeek(string $assignedDayOfWeek): static
    {
        $this->assignedDayOfWeek = $assignedDayOfWeek;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
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
            $visit->setDentist($this);
        }
        return $this;
    }

    public function removeVisit(Visit $visit): static
    {
        if ($this->visits->removeElement($visit)) {
            if ($visit->getDentist() === $this) {
                $visit->setDentist(null);
            }
        }
        return $this;
    }
}
