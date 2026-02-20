<?php

namespace App\Entity;

use App\Repository\PathologyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pathologies table (English spec).
 * Pathology_ID (PK), Description (e.g. Caries, Missing Tooth), Protocol_Color (Red/Blue).
 */
#[ORM\Entity(repositoryClass: PathologyRepository::class)]
#[ORM\Table(name: 'pathologies')]
#[ORM\Index(columns: ['description'], name: 'idx_pathology_description')]
class Pathology
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'pathology_id', type: Types::INTEGER)]
    private ?int $pathologyId = null;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $description = null;

    #[ORM\Column(name: 'protocol_color', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $protocolColor = null;

    #[ORM\OneToMany(targetEntity: OdontogramDetail::class, mappedBy: 'pathology', cascade: ['persist', 'remove'])]
    private Collection $odontogramDetails;

    public function __construct()
    {
        $this->odontogramDetails = new ArrayCollection();
    }

    public function getPathologyId(): ?int
    {
        return $this->pathologyId;
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

    public function getProtocolColor(): ?string
    {
        return $this->protocolColor;
    }

    public function setProtocolColor(string $protocolColor): static
    {
        $this->protocolColor = $protocolColor;
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
            $odontogramDetail->setPathology($this);
        }
        return $this;
    }

    public function removeOdontogramDetail(OdontogramDetail $odontogramDetail): static
    {
        if ($this->odontogramDetails->removeElement($odontogramDetail)) {
            if ($odontogramDetail->getPathology() === $this) {
                $odontogramDetail->setPathology(null);
            }
        }
        return $this;
    }
}
