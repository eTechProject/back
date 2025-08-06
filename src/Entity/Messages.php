<?php

namespace App\Entity;

use App\Repository\MessagesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\ServiceOrders;

#[ORM\Entity(repositoryClass: MessagesRepository::class)]
class Messages
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(name: 'sent_at')]
    private \DateTimeImmutable $sentAt;

    #[ORM\ManyToOne(targetEntity: ServiceOrders::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false)]
    private ServiceOrders $order;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $receiver;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

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

    public function getSender(): User
    {
        return $this->sender;
    }

    public function setSender(User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getReceiver(): User
    {
        return $this->receiver;
    }

    public function setReceiver(User $receiver): static
    {
        $this->receiver = $receiver;
        return $this;
    }
}