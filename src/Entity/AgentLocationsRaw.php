<?php

namespace App\Entity;

use App\Repository\AgentLocationsRawRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Tasks;
use App\Entity\Agents;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: AgentLocationsRawRepository::class)]
class AgentLocationsRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Position géographique de l'agent, type Point avec SRID 4326 (WGS84)
     */
    #[ORM\Column(type: PostGISType::GEOMETRY, options: ['geometry_type' => 'point', 'srid' => 4326])]
    private string $geom;

    #[ORM\Column(name: 'recorded_at')]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column]
    private float $accuracy;

    #[ORM\Column(nullable: true)]
    private ?float $speed = null;

    #[ORM\Column(nullable: true, name: 'battery_level')]
    private ?float $batteryLevel = null;

    #[ORM\Column(nullable: true, name: 'is_significant')]
    private ?bool $isSignificant = null;

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

    public function getGeom(): string
    {
        return $this->geom;
    }

    public function setGeom(string $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): static
    {
        $this->recordedAt = $recordedAt;
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
        return $this->batteryLevel;
    }

    public function setBatteryLevel(?float $batteryLevel): static
    {
        $this->batteryLevel = $batteryLevel;
        return $this;
    }

    public function isSignificant(): ?bool
    {
        return $this->isSignificant;
    }

    public function setIsSignificant(?bool $isSignificant): static
    {
        $this->isSignificant = $isSignificant;
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
