<?php

namespace App\Entity;

use App\Repository\SecuredZonesRepository;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: SecuredZonesRepository::class)]
class SecuredZones
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /**
     * Zone géographique sécurisée, type Polygon avec SRID 4326 (WGS84)
     */
    #[ORM\Column(type: PostGISType::GEOMETRY, options: ['geometry_type' => 'polygon', 'srid' => 4326])]
    private string $geom;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    /**
     * Constructeur : initialise la date de création automatiquement
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ----------------------------
    // GETTERS & SETTERS
    // ----------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getGeom(): string
    {
        return $this->geom;
    }

    public function setGeom(string $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
