<?php

namespace App\Entity;

use App\Repository\ServiceOrdersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Status;
use App\Entity\SecuredZones;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Messages;

#[ORM\Entity(repositoryClass: ServiceOrdersRepository::class)]
class ServiceOrders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: Status::class)]
    private Status $status;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: SecuredZones::class)]
    #[ORM\JoinColumn(name: 'secured_zone_id', referencedColumnName: 'id', nullable: false)]
    private SecuredZones $securedZone;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false)]
    private User $client;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Messages::class)]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = Status::PENDING;
        $this->messages = new ArrayCollection();
    }

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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSecuredZone(): SecuredZones
    {
        return $this->securedZone;
    }

    public function setSecuredZone(SecuredZones $securedZone): static
    {
        $this->securedZone = $securedZone;
        return $this;
    }

    public function getClient(): User
    {
        return $this->client;
    }

    public function setClient(User $client): static
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return Collection<int, Messages>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Messages $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setOrder($this);
        }

        return $this;
    }

    public function removeMessage(Messages $message): self
    {
        $this->messages->removeElement($message);

        return $this;
    }
}
