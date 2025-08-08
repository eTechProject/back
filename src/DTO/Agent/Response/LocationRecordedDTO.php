<?php

namespace App\DTO\Agent\Response;

use DateTimeInterface;

class LocationRecordedDTO
{
    public function __construct(
        public readonly int $locationId,
        public readonly string $recordedAt,
        public readonly bool $isSignificant,
        public readonly array $coordinates,
        public readonly float $accuracy,
        public readonly ?float $speed,
        public readonly ?float $batteryLevel,
        public readonly ?string $reason = null
    ) {}

    /**
     * Create DTO from recorded location data
     */
    public static function fromLocationData(
        int $locationId,
        DateTimeInterface $recordedAt,
        bool $isSignificant,
        float $longitude,
        float $latitude,
        float $accuracy,
        ?float $speed,
        ?float $batteryLevel,
        ?string $reason = null
    ): self {
        return new self(
            locationId: $locationId,
            recordedAt: $recordedAt->format(DateTimeInterface::ATOM),
            isSignificant: $isSignificant,
            coordinates: [
                'longitude' => $longitude,
                'latitude' => $latitude
            ],
            accuracy: $accuracy,
            speed: $speed,
            batteryLevel: $batteryLevel,
            reason: $reason
        );
    }
}
