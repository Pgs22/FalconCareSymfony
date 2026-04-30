<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    public const ALLERGY_PENICILLIN = 1;
    public const ALLERGY_LATEX = 2;
    public const ALLERGY_ANESTHESIA = 4;
    public const ALLERGY_NSAIDS = 8;
    public const ALLERGY_CHLORHEXIDINE = 16;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $identityDocument = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ssNumber = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 100)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $consultationReason = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $familyHistory = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $healthStatus = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $lifestyleHabits = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $registrationDate = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $medicationAllergies = null;

    #[ORM\Column(name: 'allergies_bitmask', type: Types::INTEGER, options: ['default' => 0])]
    private int $allergiesBitmask = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $profileImage = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastOdontogramId = null;

    /**
     * @var Collection<int, Appointment>
     */
    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'patient')]
    private Collection $appointments;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'patient')]
    private Collection $documents;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
        $this->documents = new ArrayCollection();
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
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentityDocument(): ?string
    {
        return $this->identityDocument;
    }

    public function setIdentityDocument(string $identityDocument): static
    {
        $this->identityDocument = $identityDocument;

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

    public function getSsNumber(): ?string
    {
        return $this->ssNumber;
    }

    public function setSsNumber(?string $ssNumber): static
    {
        $this->ssNumber = $ssNumber;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

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

    public function getFamilyHistory(): ?string
    {
        return $this->familyHistory;
    }

    public function setFamilyHistory(string $familyHistory): static
    {
        $this->familyHistory = $familyHistory;

        return $this;
    }

    public function getHealthStatus(): ?string
    {
        return $this->healthStatus;
    }

    public function setHealthStatus(string $healthStatus): static
    {
        $this->healthStatus = $healthStatus;

        return $this;
    }

    public function getLifestyleHabits(): ?string
    {
        return $this->lifestyleHabits;
    }

    public function setLifestyleHabits(string $lifestyleHabits): static
    {
        $this->lifestyleHabits = $lifestyleHabits;

        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeImmutable
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTimeImmutable $registrationDate): static
    {
        $this->registrationDate = $registrationDate;

        return $this;
    }

    public function getMedicationAllergies(): ?string
    {
        return $this->medicationAllergies;
    }

    public function setMedicationAllergies(string $medicationAllergies): static
    {
        $this->medicationAllergies = $medicationAllergies;

        return $this;
    }

    public function getAllergiesBitmask(): int
    {
        return $this->allergiesBitmask;
    }

    public function setAllergiesBitmask(int $allergiesBitmask): static
    {
        $this->allergiesBitmask = $allergiesBitmask;

        return $this;
    }

    public function hasAllergy(int $allergy): bool
    {
        return ($this->allergiesBitmask & $allergy) === $allergy;
    }

    public function addAllergy(int $allergy): static
    {
        $this->allergiesBitmask |= $allergy;

        return $this;
    }

    public function removeAllergy(int $allergy): static
    {
        $this->allergiesBitmask &= ~$allergy;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getSelectedAllergies(): array
    {
        $selectedAllergies = [];

        foreach (self::getAllergyCatalog() as $allergy => $label) {
            if ($this->hasAllergy($allergy)) {
                $selectedAllergies[] = $allergy;
            }
        }

        return $selectedAllergies;
    }

    /**
     * @param array<int, int|string> $selectedAllergies
     */
    public function setSelectedAllergies(array $selectedAllergies): static
    {
        $this->allergiesBitmask = self::buildAllergiesBitmask($selectedAllergies);

        return $this;
    }

    /**
     * @param array<int, int|string> $selectedAllergies
     */
    public static function buildAllergiesBitmask(array $selectedAllergies): int
    {
        $bitmask = 0;

        foreach ($selectedAllergies as $allergy) {
            $flag = (int) $allergy;
            if (array_key_exists($flag, self::getAllergyCatalog())) {
                $bitmask |= $flag;
            }
        }

        return $bitmask;
    }

    /**
     * @return array<int, string>
     */
    public static function getAllergyCatalog(): array
    {
        return [
            self::ALLERGY_PENICILLIN => 'Penicillin',
            self::ALLERGY_LATEX => 'Latex',
            self::ALLERGY_ANESTHESIA => 'Anesthesia',
            self::ALLERGY_NSAIDS => 'NSAIDs',
            self::ALLERGY_CHLORHEXIDINE => 'Chlorhexidine',
        ];
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): static
    {
        $this->profileImage = $profileImage;

        return $this;
    }

    /**
     * Returns the same collection as getAppointments() (alias for compatibility).
     *
     * @return Collection<int, Appointment>
     */
    public function getDoctor(): Collection
    {
        return $this->appointments;
    }

    public function addDoctor(Appointment $doctor): static
    {
        return $this->addAppointment($doctor);
    }

    public function removeDoctor(Appointment $doctor): static
    {
        return $this->removeAppointment($doctor);
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
            $appointment->setPatient($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            // set the owning side to null (unless already changed)
            if ($appointment->getPatient() === $this) {
                $appointment->setPatient(null);
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
            // set the owning side to null (unless already changed)
            if ($document->getPatient() === $this) {
                $document->setPatient(null);
            }
        }

        return $this;
    }
}
