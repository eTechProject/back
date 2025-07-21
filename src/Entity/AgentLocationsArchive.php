<?php

namespace App\Entity;

use App\Repository\AgentLocationsArchiveRepository;
use Doctrine\ORM\Mapping as ORM;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;

#[ORM\Entity(repositoryClass: AgentLocationsArchiveRepository::class)]
class AgentLocationsArchive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'geometry', options: ['geometry_type' => 'LINESTRING', 'srid' => 4326])]
    private ?LineString $geom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $start_time = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $end_time = null;

    #[ORM\Column]
    private ?int $point_count = null;

    #[ORM\Column]
    private ?float $avg_speed = null;

    #[ORM\Column]
    private ?float $path_length = null;

    #[ORM\Column]
    private ?int $agent_id = null;

    #[ORM\Column]
    private ?int $task_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getGeom(): ?LineString
    {
        return $this->geom;
    }

    public function setGeom(LineString $geom): static
    {
        $this->geom = $geom;
        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->start_time;
    }

    public function setStartTime(\DateTimeImmutable $start_time): static
    {
        $this->start_time = $start_time;
        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->end_time;
    }

    public function setEndTime(\DateTimeImmutable $end_time): static
    {
        $this->end_time = $end_time;
        return $this;
    }

    public function getPointCount(): ?int
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

    public function setAvgSpeed(float $avg_speed): static
    {
        $this->avg_speed = $avg_speed;
        return $this;
    }

    public function getPathLength(): ?float
    {
        return $this->path_length;
    }

    public function setPathLength(float $path_length): static
    {
        $this->path_length = $path_length;
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

    public function getTaskId(): ?int
    {
        return $this->task_id;
    }

    public function setTaskId(int $task_id): static
    {
        $this->task_id = $task_id;
        return $this;
    }
}
