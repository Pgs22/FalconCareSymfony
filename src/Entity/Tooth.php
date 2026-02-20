<?php

namespace App\Entity;

use App\Repository\ToothRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ToothRepository::class)]
#[ORM\Table(name: 'teeth')]
#[ORM\Index(columns: ['description'], name: 'idx_tooth_description')]
class Tooth
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'tooth_id', type: Types::INTEGER)]
    private ?int $toothId = null;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $description = null;

    #[ORM\OneToMany(targetEntity: OdontogramDetail::class, mappedBy: 'tooth', cascade: ['persist', 'remove'])]
    private Collection $odontogramDetails;

    public function __construct()
    {
        $this->odontogramDetails = new ArrayCollection();
    }

    public function getToothId(): ?int
    {
        return $this->toothId;
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
            $odontogramDetail->setTooth($this);
        }
        return $this;
    }

    public function removeOdontogramDetail(OdontogramDetail $odontogramDetail): static
    {
        if ($this->odontogramDetails->removeElement($odontogramDetail)) {
            if ($odontogramDetail->getTooth() === $this) {
                $odontogramDetail->setTooth(null);
            }
        }
        return $this;
    }
}
