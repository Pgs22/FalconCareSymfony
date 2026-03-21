<?php

namespace App\Entity;

use App\Repository\PathologyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PathologyRepository::class)]
class Pathology
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    private ?string $protocolColor = null;

    /**
     * @var Collection<int, Treatment>
     */
    #[ORM\ManyToOne(targetEntity: Treatment::class, inversedBy: 'pathologies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Treatment $treatment = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $visualType = null;

    /**
     * @var Collection<int, OdontogramDetail>
     */
    #[ORM\OneToMany(targetEntity: OdontogramDetail::class, mappedBy: 'pathology')]
    private Collection $odontogramDetails;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PathologyType $pathology_type = null;    

    public function __construct()
    {
        $this->odontogramDetails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, Treatment>
     */
    public function getTreatment(): Collection
    {
        return $this->treatment;
    }

    public function setTreatment(?Treatment $treatment): static
    {
        $this->treatment = $treatment;

        return $this;
    }

    public function getVisualType(): ?string
    {
        return $this->visualType;
    }

    public function setVisualType(?string $visualType): static
    {
        $this->visualType = $visualType;

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

    public function getPathologyType(): ?PathologyType
    {
        return $this->pathology_type;
    }

    public function setPathologyType(?PathologyType $pathology_type): static
    {
        $this->pathology_type = $pathology_type;

        return $this;
    }

}
