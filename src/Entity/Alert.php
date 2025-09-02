<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\AlertType;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'iduser', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ServiceOrders::class)]
    #[ORM\JoinColumn(name: 'idorder', referencedColumnName: 'id', nullable: false)]
    private ?ServiceOrders $order = null;

    #[ORM\Column(type: Types::STRING, enumType: AlertType::class, length: 32)]
    private AlertType $type;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $timestamp;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getOrder(): ?ServiceOrders { return $this->order; }
    public function setOrder(ServiceOrders $order): self { $this->order = $order; return $this; }
    public function getType(): AlertType { return $this->type; }
    public function setType(AlertType $type): self { $this->type = $type; return $this; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
    public function setTimestamp(\DateTimeImmutable $timestamp): self { $this->timestamp = $timestamp; return $this; }    
}
