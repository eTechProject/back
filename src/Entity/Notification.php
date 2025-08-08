<?php

namespace App\Entity;

use App\Enum\NotificationType;
use App\Enum\NotificationTarget;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: 'App\Repository\NotificationRepository')]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    #[Groups(['notification:read'])]
    private string $titre;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['notification:read'])]
    private string $message;

    #[ORM\Column(type: 'string', length: 50, enumType: NotificationType::class)]
    #[Groups(['notification:read'])]
    private NotificationType $type = NotificationType::INFO;

    #[ORM\Column(type: 'string', length: 20, enumType: NotificationTarget::class)]
    #[Groups(['notification:read'])]
    private NotificationTarget $cible = NotificationTarget::USER;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['notification:read'])]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['notification:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['notification:read'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCible(): NotificationTarget
    {
        return $this->cible;
    }

    public function setCible(NotificationTarget $cible): static
    {
        $this->cible = $cible;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
