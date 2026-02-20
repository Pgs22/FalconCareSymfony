<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Patient table (English spec).
 * Patient_ID (PK), National_ID (Unique), First_Name, Last_Name, Social_Security_Number,
 * Phone, Email, Address, Billing_Information, Reason_for_Consultation,
 * Family_History, Health_Status, Lifestyle_Habits, Medication_Allergies, Registration_Date.
 */
#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\Table(name: 'patients')]
#[ORM\Index(columns: ['national_id'], name: 'idx_patient_national_id')]
#[ORM\Index(columns: ['email'], name: 'idx_patient_email')]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'patient_id', type: Types::INTEGER)]
    private ?int $patientId = null;

    #[ORM\Column(name: 'national_id', type: Types::STRING, length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $nationalId = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'social_security_number', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $socialSecurityNumber = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\Column(name: 'address', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(name: 'billing_information', type: Types::TEXT, nullable: true)]
    private ?string $billingInformation = null;

    #[ORM\Column(name: 'reason_for_consultation', type: Types::TEXT, nullable: true)]
    private ?string $reasonForConsultation = null;

    #[ORM\Column(name: 'family_history', type: Types::TEXT, nullable: true)]
    private ?string $familyHistory = null;

    #[ORM\Column(name: 'health_status', type: Types::TEXT, nullable: true)]
    private ?string $healthStatus = null;

    #[ORM\Column(name: 'lifestyle_habits', type: Types::TEXT, nullable: true)]
    private ?string $lifestyleHabits = null;

    #[ORM\Column(name: 'medication_allergies', type: Types::TEXT, nullable: true)]
    private ?string $medicationAllergies = null;

    #[ORM\Column(name: 'registration_date', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\OneToMany(targetEntity: Visit::class, mappedBy: 'patient', cascade: ['persist', 'remove'])]
    private Collection $visits;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'patient', cascade: ['persist', 'remove'])]
    private Collection $documents;

    public function __construct()
    {
        $this->visits = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->registrationDate = new \DateTime();
    }

    public function getPatientId(): ?int
    {
        return $this->patientId;
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function setNationalId(string $nationalId): static
    {
        $this->nationalId = $nationalId;
        return $this;
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

    public function getSocialSecurityNumber(): ?string
    {
        return $this->socialSecurityNumber;
    }

    public function setSocialSecurityNumber(?string $socialSecurityNumber): static
    {
        $this->socialSecurityNumber = $socialSecurityNumber;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getBillingInformation(): ?string
    {
        return $this->billingInformation;
    }

    public function setBillingInformation(?string $billingInformation): static
    {
        $this->billingInformation = $billingInformation;
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

    public function getFamilyHistory(): ?string
    {
        return $this->familyHistory;
    }

    public function setFamilyHistory(?string $familyHistory): static
    {
        $this->familyHistory = $familyHistory;
        return $this;
    }

    public function getHealthStatus(): ?string
    {
        return $this->healthStatus;
    }

    public function setHealthStatus(?string $healthStatus): static
    {
        $this->healthStatus = $healthStatus;
        return $this;
    }

    public function getLifestyleHabits(): ?string
    {
        return $this->lifestyleHabits;
    }

    public function setLifestyleHabits(?string $lifestyleHabits): static
    {
        $this->lifestyleHabits = $lifestyleHabits;
        return $this;
    }

    public function getMedicationAllergies(): ?string
    {
        return $this->medicationAllergies;
    }

    public function setMedicationAllergies(?string $medicationAllergies): static
    {
        $this->medicationAllergies = $medicationAllergies;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTimeInterface $registrationDate): static
    {
        $this->registrationDate = $registrationDate;
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
            $visit->setPatient($this);
        }
        return $this;
    }

    public function removeVisit(Visit $visit): static
    {
        if ($this->visits->removeElement($visit)) {
            if ($visit->getPatient() === $this) {
                $visit->setPatient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setPatient($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getPatient() === $this) {
                $document->setPatient(null);
            }
        }
        return $this;
    }
}
