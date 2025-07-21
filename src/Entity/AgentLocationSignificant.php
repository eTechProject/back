<?php

namespace App\Entity;

use App\Repository\AgentLocationSignificantRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Reason;
use CrEOF\Spatial\PHP\Types\Geometry\Polygon;

#[ORM\Entity(repositoryClass: AgentLocationSignificantRepository::class)]
class AgentLocationSignificant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'polygon', options: ['srid' => 4326])]
    private ?Polygon $geom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $recorded_at = null;

    #[ORM\Column(length: 20, enumType: Reason::class)]
    private ?Reason $reason = null;

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

    public function getGeom(): ?string
    {
        return $this->geom;
    }

    public function setGeom(string $geom): static
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

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
