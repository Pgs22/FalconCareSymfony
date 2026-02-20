<?php

namespace App\Entity;

use App\Repository\BoxRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum BoxStatus: string
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
}

/**
 * Boxes table (English spec).
 * Box_ID (PK), Box_Name (e.g. "Box 1"), Status (Active/Inactive), Capacity.
 */
#[ORM\Entity(repositoryClass: BoxRepository::class)]
#[ORM\Table(name: 'boxes')]
#[ORM\Index(columns: ['status'], name: 'idx_box_status')]
class Box
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_box', type: Types::INTEGER)]
    private ?int $idBox = null;

    #[ORM\Column(name: 'box_name', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $boxName = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: BoxStatus::class)]
    #[Assert\NotBlank]
    private BoxStatus $status;

    #[ORM\Column(name: 'capacity', type: Types::INTEGER, options: ['default' => 2])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $capacity = 2;

    #[ORM\OneToMany(targetEntity: Visit::class, mappedBy: 'box', cascade: ['persist', 'remove'])]
    private Collection $visits;

    public function __construct()
    {
        $this->visits = new ArrayCollection();
        $this->status = BoxStatus::ACTIVE;
    }

    public function getIdBox(): ?int
    {
        return $this->idBox;
    }

    public function getBoxName(): ?string
    {
        return $this->boxName;
    }

    public function setBoxName(string $boxName): static
    {
        $this->boxName = $boxName;
        return $this;
    }

    public function getStatus(): BoxStatus
    {
        return $this->status;
    }

    public function setStatus(BoxStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
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
            $visit->setBox($this);
        }
        return $this;
    }

    public function removeVisit(Visit $visit): static
    {
        if ($this->visits->removeElement($visit)) {
            if ($visit->getBox() === $this) {
                $visit->setBox(null);
            }
        }
        return $this;
    }
}
