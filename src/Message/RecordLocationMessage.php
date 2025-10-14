<?php

namespace App\Message;

/**
 * Message for queuing location recording tasks
 */
class RecordLocationMessage
{
    public function __construct(
        private readonly string $encryptedUserId,
        private readonly float $longitude,
        private readonly float $latitude,
        private readonly float $accuracy,
        private readonly ?float $speed,
        private readonly ?float $batteryLevel,
        private readonly ?bool $isSignificant,
        private readonly ?string $reason,
        private readonly string $taskId,
        private readonly \DateTimeImmutable $requestedAt
    ) {}

    public function getEncryptedUserId(): string
    {
        return $this->encryptedUserId;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getAccuracy(): float
    {
        return $this->accuracy;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function getBatteryLevel(): ?float
    {
        return $this->batteryLevel;
    }

    public function getIsSignificant(): ?bool
    {
        return $this->isSignificant;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    /**
     * Get unique identifier for this message (for deduplication)
     */
    public function getUniqueId(): string
    {
        return sprintf(
            '%s_%s_%.6f_%.6f_%s',
            $this->encryptedUserId,
            $this->taskId,
            $this->longitude,
            $this->latitude,
            $this->requestedAt->format('YmdHis')
        );
    }
}