<?php

namespace App\Entity;

use App\Repository\AgentLocationsRawRepository;
use Doctrine\ORM\Mapping as ORM;
use CrEOF\Spatial\PHP\Types\Geometry\Polygon;

#[ORM\Entity(repositoryClass: AgentLocationsRawRepository::class)]
class AgentLocationsRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'polygon', options: ['srid' => 4326])]
    private ?Polygon $geom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $recorded_at = null;

    #[ORM\Column]
    private ?float $accuracy = null;

    #[ORM\Column]
    private ?float $speed = null;

    #[ORM\Column]
    private ?float $battery_level = null;

    #[ORM\Column]
    private ?bool $is_significant = null;

    #[ORM\Column]
    private ?int $tasks_id = null;

    #[ORM\Column]
    private ?int $agent_id = null;

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getGeom(): ?Polygon
    {
        return $this->geom;
    }

    public function setGeom(?Polygon $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recorded_at;
    }

    public function setRecordedAt(\DateTimeImmutable $recorded_at): static
    {
        $this->recorded_at = $recorded_at;
        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(float $accuracy): static
    {
        $this->accuracy = $accuracy;
        return $this;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function setSpeed(float $speed): static
    {
        $this->speed = $speed;
        return $this;
    }

    public function getBatteryLevel(): ?float
    {
        return $this->battery_level;
    }

    public function setBatteryLevel(float $battery_level): static
    {
        $this->battery_level = $battery_level;
        return $this;
    }

    public function isSignificant(): ?bool
    {
        return $this->is_significant;
    }

    public function setIsSignificant(bool $is_significant): static
    {
        $this->is_significant = $is_significant;
        return $this;
    }

    public function getTasksId(): ?int
    {
        return $this->tasks_id;
    }

    public function setTasksId(int $tasks_id): static
    {
        $this->tasks_id = $tasks_id;
        return $this;
    }

    public function getAgentId(): ?int
    {
        return $this->agent_id;
    }

    public function setAgentId(int $agent_id): static
    {
        $this->agent_id = $agent_id;
        return $this;
    }
}
