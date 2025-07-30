<?php

namespace App\Entity;

use App\Repository\TasksRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ServiceOrders;
use App\Entity\Agents;
use App\Enum\Status;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: TasksRepository::class)]
class Tasks
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: Status::class)]
    private Status $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $end_date = null;

    #[ORM\Column]
    private \DateTimeImmutable $start_date;

    /**
     * Position d'assignation de la tâche, type Point avec SRID 4326 (WGS84)
     */
    #[ORM\Column(type: PostGISType::GEOMETRY, options: ['geometry_type' => 'point', 'srid' => 4326])]
    private string $assign_position;

    #[ORM\ManyToOne(targetEntity: ServiceOrders::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false)]
    private ServiceOrders $order;

    #[ORM\ManyToOne(targetEntity: Agents::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: false)]
    private Agents $agent;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTimeImmutable $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTimeImmutable $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getOrder(): ServiceOrders
    {
        return $this->order;
    }

    public function setOrder(ServiceOrders $order): static
    {
        $this->order = $order;

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

    public function getAssignPosition(): string
    {
        return $this->assign_position;
    }

    public function setAssignPosition(string $assign_position): static
    {
        $this->assign_position = $assign_position;

        return $this;
    }
}
