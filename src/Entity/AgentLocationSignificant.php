<?php

namespace App\Entity;

use App\Repository\AgentLocationSignificantRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Reason;
use App\Entity\Agents;
use App\Entity\Tasks;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: AgentLocationSignificantRepository::class)]
class AgentLocationSignificant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Position géographique significative de l'agent, type Point avec SRID 4326 (WGS84)
     */
    #[ORM\Column(type: PostGISType::GEOMETRY, options: ['geometry_type' => 'point', 'srid' => 4326])]
    private string $geom;

    #[ORM\Column]
    private \DateTimeImmutable $recorded_at;

    #[ORM\Column(enumType: Reason::class)]
    private Reason $reason;

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

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recorded_at;
    }

    public function setRecordedAt(\DateTimeImmutable $recorded_at): static
    {
        $this->recorded_at = $recorded_at;

        return $this;
    }

    public function getReason(): Reason
    {
        return $this->reason;
    }

    public function setReason(Reason $reason): static
    {
        $this->reason = $reason;

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
