<?php

namespace App\Entity;

use App\Repository\OdontogramDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OdontogramDetailRepository::class)]
#[ORM\Table(name: 'odontogram_details')]
#[ORM\Index(columns: ['visit_id'], name: 'idx_odontogram_visit')]
#[ORM\Index(columns: ['tooth_id'], name: 'idx_odontogram_tooth')]
#[ORM\Index(columns: ['pathology_id'], name: 'idx_odontogram_pathology')]
class OdontogramDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'detail_id', type: Types::INTEGER)]
    private ?int $detailId = null;

    #[ORM\ManyToOne(targetEntity: Visit::class, inversedBy: 'odontogramDetails')]
    #[ORM\JoinColumn(name: 'visit_id', referencedColumnName: 'id_visit', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Visit $visit = null;

    #[ORM\ManyToOne(targetEntity: Tooth::class, inversedBy: 'odontogramDetails')]
    #[ORM\JoinColumn(name: 'tooth_id', referencedColumnName: 'tooth_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Tooth $tooth = null;

    #[ORM\ManyToOne(targetEntity: Pathology::class, inversedBy: 'odontogramDetails')]
    #[ORM\JoinColumn(name: 'pathology_id', referencedColumnName: 'pathology_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Pathology $pathology = null;

    #[ORM\Column(name: 'tooth_surface', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $toothSurface = null;

    /** For optional 3D functionality (Vestibular, Occlusal, etc.) */
    #[ORM\Column(name: 'coordinates_3d', type: Types::JSON, nullable: true)]
    private ?array $coordinates3d = null;

    public function getDetailId(): ?int
    {
        return $this->detailId;
    }

    public function getVisit(): ?Visit
    {
        return $this->visit;
    }

    public function setVisit(?Visit $visit): static
    {
        $this->visit = $visit;
        return $this;
    }

    public function getTooth(): ?Tooth
    {
        return $this->tooth;
    }

    public function setTooth(?Tooth $tooth): static
    {
        $this->tooth = $tooth;
        return $this;
    }

    public function getPathology(): ?Pathology
    {
        return $this->pathology;
    }

    public function setPathology(?Pathology $pathology): static
    {
        $this->pathology = $pathology;
        return $this;
    }

    public function getToothSurface(): ?string
    {
        return $this->toothSurface;
    }

    public function setToothSurface(string $toothSurface): static
    {
        $this->toothSurface = $toothSurface;
        return $this;
    }

    public function getCoordinates3d(): ?array
    {
        return $this->coordinates3d;
    }

    public function setCoordinates3d(?array $coordinates3d): static
    {
        $this->coordinates3d = $coordinates3d;
        return $this;
    }
}
