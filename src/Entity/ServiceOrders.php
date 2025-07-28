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

    #[ORM\Column]
    private \DateTimeImmutable $created_at;

    #[ORM\ManyToOne(targetEntity: SecuredZones::class)]
    #[ORM\JoinColumn(name: 'secured_zone_id', referencedColumnName: 'id', nullable: false)]
    private SecuredZones $secured_zone;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false)]
    private User $client;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Messages::class)]
    private Collection $messages;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
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
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getSecuredZone(): SecuredZones
    {
        return $this->secured_zone;
    }

    public function setSecuredZone(SecuredZones $secured_zone): static
    {
        $this->secured_zone = $secured_zone;
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
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getOrder() === $this) {
                $message->setOrder(null);
            }
        }

        return $this;
    }
}
