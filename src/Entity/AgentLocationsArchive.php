<?php

namespace App\Entity;

use App\Repository\AgentLocationsArchiveRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Agents;
use App\Entity\Tasks;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;

#[ORM\Entity(repositoryClass: AgentLocationsArchiveRepository::class)]
class AgentLocationsArchive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'linestring', options: ['srid' => 4326])]
    private LineString $geom;

    #[ORM\Column]
    private \DateTimeImmutable $start_time;

    #[ORM\Column]
    private \DateTimeImmutable $end_time;

    #[ORM\Column]
    private int $point_count;

    #[ORM\Column(nullable: true)]
    private ?float $avg_speed = null;

    #[ORM\Column(nullable: true)]
    private ?float $path_length = null;

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

    public function getGeom(): LineString
    {
        return $this->geom;
    }

    public function setGeom(LineString $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->start_time;
    }

    public function setStartTime(\DateTimeImmutable $start_time): static
    {
        $this->start_time = $start_time;
        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->end_time;
    }

    public function setEndTime(\DateTimeImmutable $end_time): static
    {
        $this->end_time = $end_time;
        return $this;
    }

    public function getPointCount(): int
    {
        return $this->point_count;
    }

    public function setPointCount(int $point_count): static
    {
        $this->point_count = $point_count;
        return $this;
    }

    public function getAvgSpeed(): ?float
    {
        return $this->avg_speed;
    }

    public function setAvgSpeed(?float $avg_speed): static
    {
        $this->avg_speed = $avg_speed;
        return $this;
    }

    public function getPathLength(): ?float
    {
        return $this->path_length;
    }

    public function setPathLength(?float $path_length): static
    {
        $this->path_length = $path_length;
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
