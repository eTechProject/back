<?php

namespace App\Exception;

/**
 * Exception thrown when location recording fails
 */
class LocationRecordingException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $encryptedUserId = null,
        private readonly ?string $taskId = null,
        private readonly ?array $coordinates = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getEncryptedUserId(): ?string
    {
        return $this->encryptedUserId;
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function getCoordinates(): ?array
    {
        return $this->coordinates;
    }

    public function getContext(): array
    {
        return array_filter([
            'encrypted_user_id' => $this->encryptedUserId,
            'task_id' => $this->taskId,
            'coordinates' => $this->coordinates,
        ]);
    }
}