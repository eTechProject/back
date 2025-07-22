<?php

namespace App\Entity;

use App\Repository\AgentLocationsRawRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Tasks;
use App\Entity\Agents;
use CrEOF\Spatial\PHP\Types\Geometry\Point;

#[ORM\Entity(repositoryClass: AgentLocationsRawRepository::class)]
class AgentLocationsRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'point', options: ['srid' => 4326])]
    private Point $geom;

    #[ORM\Column]
    private \DateTimeImmutable $recorded_at;

    #[ORM\Column]
    private float $accuracy;

    #[ORM\Column(nullable: true)]
    private ?float $speed = null;

    #[ORM\Column(nullable: true)]
    private ?float $battery_level = null;

    #[ORM\Column(nullable: true)]
    private ?bool $is_significant = null;

    #[ORM\ManyToOne(targetEntity: Tasks::class)]
    #[ORM\JoinColumn(name: 'tasks_id', referencedColumnName: 'id', nullable: false)]
    private Tasks $task;

    #[ORM\ManyToOne(targetEntity: Agents::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: false)]
    private Agents $agent;

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

    public function getGeom(): Point
    {
        return $this->geom;
    }

    public function setGeom(Point $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recorded_at;
    }

    public function setRecordedAt(\DateTimeImmutable $recorded_at): static
    {
        $this->recorded_at = $recorded_at;
        return $this;
    }

    public function getAccuracy(): float
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

    public function setSpeed(?float $speed): static
    {
        $this->speed = $speed;
        return $this;
    }

    public function getBatteryLevel(): ?float
    {
        return $this->battery_level;
    }

    public function setBatteryLevel(?float $battery_level): static
    {
        $this->battery_level = $battery_level;
        return $this;
    }

    public function isSignificant(): ?bool
    {
        return $this->is_significant;
    }

    public function setIsSignificant(?bool $is_significant): static
    {
        $this->is_significant = $is_significant;
        return $this;
    }

    public function getTask(): Tasks
    {
        return $this->task;
    }

    public function setTask(Tasks $task): static
    {
        $this->task = $task;
        return $this;
    }

    public function getAgent(): Agents
    {
        return $this->agent;
    }

    public function setAgent(Agents $agent): static
    {
        $this->agent = $agent;
        return $this;
    }
}
