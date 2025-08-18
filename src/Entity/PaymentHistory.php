<?php

namespace App\Entity;

use App\Repository\PaymentHistoryRepository;
use App\Enum\PaymentHistoryStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentHistoryRepository::class)]
#[ORM\Table(name: 'payment_history')]
class PaymentHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(name: 'payment_id', nullable: false)]
    #[Assert\NotNull(message: 'Le payment est requis')]
    private Payment $payment;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date est requise')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'cybersource'])]
    #[Assert\NotBlank(message: 'Le provider est requis')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le provider ne peut pas dépasser {{ limit }} caractères'
    )]
    private string $provider = 'cybersource';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le montant est requis')]
    #[Assert\PositiveOrZero(message: 'Le montant doit être positif ou nul')]
    private string $amount;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: PaymentHistoryStatus::class)]
    #[Assert\NotNull(message: 'Le statut est requis')]
    private PaymentHistoryStatus $status;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    // New field: providerResponse (nullable) to store provider session/token/etc.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $providerResponse = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderResponse(): ?string
    {
        return $this->providerResponse;
    }

    public function setProviderResponse(?string $providerResponse): static
    {
        $this->providerResponse = $providerResponse;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStatus(): PaymentHistoryStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentHistoryStatus $status): static
    {
        $this->status = $status;
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

    public function isSuccess(): bool
    {
        return $this->status === PaymentHistoryStatus::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentHistoryStatus::FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentHistoryStatus::PENDING;
    }

    public function getAmountAsFloat(): float
    {
        return (float) $this->amount;
    }
}
