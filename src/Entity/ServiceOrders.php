<?php

namespace App\Entity;

use App\Repository\ServiceOrdersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Status; // Assure-toi que le namespace est correct

#[ORM\Entity(repositoryClass: ServiceOrdersRepository::class)]
class ServiceOrders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    // Relations recommandées (remplace par ManyToOne si tu as les entités)
    #[ORM\Column]
    private ?int $secured_zone_id = null;

    #[ORM\Column]
    private ?int $client_id = null;

    // Getters et setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getSecuredZoneId(): ?int
    {
        return $this->secured_zone_id;
    }

    public function setSecuredZoneId(int $secured_zone_id): static
    {
        $this->secured_zone_id = $secured_zone_id;
        return $this;
    }

    public function getClientId(): ?int
    {
        return $this->client_id;
    }

    public function setClientId(int $client_id): static
    {
        $this->client_id = $client_id;
        return $this;
    }
}
