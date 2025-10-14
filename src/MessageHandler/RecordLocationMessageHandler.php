<?php

namespace App\MessageHandler;

use App\DTO\Agent\Request\RecordLocationDTO;
use App\Exception\LocationRecordingException;
use App\Message\RecordLocationMessage;
use App\Service\AgentLocationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handler for processing location recording messages asynchronously
 */
#[AsMessageHandler]
class RecordLocationMessageHandler
{
    public function __construct(
        private readonly AgentLocationService $agentLocationService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(RecordLocationMessage $message): void
    {
        $context = [
            'encrypted_user_id' => $message->getEncryptedUserId(),
            'task_id' => $message->getTaskId(),
            'coordinates' => [$message->getLongitude(), $message->getLatitude()],
            'is_significant' => $message->getIsSignificant(),
            'reason' => $message->getReason(),
            'requested_at' => $message->getRequestedAt()->format(\DateTimeInterface::ATOM),
            'message_unique_id' => $message->getUniqueId()
        ];

        $this->logger->info('Processing location recording message', $context);

        try {
            // Create RecordLocationDTO from message data
            $locationData = new RecordLocationDTO(
                longitude: $message->getLongitude(),
                latitude: $message->getLatitude(),
                accuracy: $message->getAccuracy(),
                speed: $message->getSpeed(),
                batteryLevel: $message->getBatteryLevel(),
                isSignificant: $message->getIsSignificant(),
                reason: $message->getReason(),
                taskId: $message->getTaskId()
            );

            // Process the location recording
            $rawLocation = $this->agentLocationService->recordLocation(
                $message->getEncryptedUserId(),
                $locationData
            );

            $this->logger->info('Location recording completed successfully', array_merge($context, [
                'location_id' => $rawLocation->getId(),
                'processing_duration' => $this->calculateProcessingDuration($message->getRequestedAt())
            ]));

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid location data in message', array_merge($context, [
                'error' => $e->getMessage(),
                'error_type' => 'validation_error'
            ]));
            
            // Don't retry validation errors - they won't resolve
            throw new UnrecoverableMessageHandlingException(
                'Invalid location data: ' . $e->getMessage(),
                0,
                $e
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to process location recording message', array_merge($context, [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode()
            ]));
            
            // Wrap in our custom exception for better context
            throw new LocationRecordingException(
                sprintf('Failed to record location: %s', $e->getMessage()),
                $message->getEncryptedUserId(),
                $message->getTaskId(),
                [$message->getLongitude(), $message->getLatitude()],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Calculate processing duration in milliseconds
     */
    private function calculateProcessingDuration(\DateTimeImmutable $requestedAt): int
    {
        $now = new \DateTimeImmutable();
        return (int) (($now->getTimestamp() - $requestedAt->getTimestamp()) * 1000 +
                     ($now->format('u') - $requestedAt->format('u')) / 1000);
    }
}