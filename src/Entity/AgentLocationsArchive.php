<?php

namespace App\Entity;

use App\Repository\AgentLocationsArchiveRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Agents;
use App\Entity\Tasks;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: AgentLocationsArchiveRepository::class)]
class AgentLocationsArchive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Trajectoire de l'agent archivée, type LineString avec SRID 4326 (WGS84)
     */
    #[ORM\Column(type: PostGISType::GEOMETRY, options: ['geometry_type' => 'linestring', 'srid' => 4326])]
    private string $geom;

    #[ORM\Column(name: 'start_time')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(name: 'end_time')]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(name: 'point_count')]
    private int $pointCount;

    #[ORM\Column(nullable: true, name: 'avg_speed')]
    private ?float $avgSpeed = null;

    #[ORM\Column(nullable: true, name: 'path_length')]
    private ?float $pathLength = null;

    #[ORM\ManyToOne(targetEntity: Agents::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: false)]
    private Agents $agent;

    #[ORM\ManyToOne(targetEntity: Tasks::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false)]
    private Tasks $task;

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

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getPointCount(): int
    {
        return $this->pointCount;
    }

    public function setPointCount(int $pointCount): static
    {
        $this->pointCount = $pointCount;
        return $this;
    }

    public function getAvgSpeed(): ?float
    {
        return $this->avgSpeed;
    }

    public function setAvgSpeed(?float $avgSpeed): static
    {
        $this->avgSpeed = $avgSpeed;
        return $this;
    }

    public function getPathLength(): ?float
    {
        return $this->pathLength;
    }

    public function setPathLength(?float $pathLength): static
    {
        $this->pathLength = $pathLength;
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

    public function getTask(): Tasks
    {
        return $this->task;
    }

    public function setTask(Tasks $task): static
    {
        $this->task = $task;
        return $this;
    }
}
