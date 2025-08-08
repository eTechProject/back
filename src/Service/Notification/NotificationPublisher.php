<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\DTO\Notification\Response\NotificationResponseDTO;
use App\Service\CryptService;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;

class NotificationPublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly CryptService $cryptService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Publie une notification en temps réel via Mercure
     */
    public function publishNotification(Notification $notification): void
    {
        try {
            $data = json_encode([
                'type' => 'notification',
                'data' => $this->convertToDTO($notification)
            ]);

            $topics = ['/notifications'];
            if ($notification->getUser()) {
                $encryptedUserId = $this->cryptService->encryptId($notification->getUser()->getId(), 'user');
                $topics[] = "/users/{$encryptedUserId}/notifications";
            }

            $update = new Update(
                topics: $topics,
                data: $data,
                private: true
            );

            $this->hub->publish($update);

        } catch (\Exception $e) {
            $this->logger->error('Failed to publish notification via Mercure', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Publie des données à un utilisateur spécifique
     * Topic: /users/{id}/notifications
     */
    public function publishToUser(int $userId, array $data): void
    {
        try {
            $encryptedUserId = $this->cryptService->encryptId($userId, 'user');
            
            $serializedData = json_encode($data);

            $update = new Update(
                topics: ["/users/{$encryptedUserId}/notifications"],
                data: $serializedData,
                private: true
            );

            $this->hub->publish($update);

            $this->logger->info('Data published to user via Mercure', [
                'user_id' => $userId,
                'encrypted_user_id' => $encryptedUserId,
                'data_type' => $data['type'] ?? 'unknown',
                'topic' => "/users/{$encryptedUserId}/notifications"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to publish data to user via Mercure', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Exemple d'utilisation : Publier une mise à jour de statut à un utilisateur
     */
    public function publishStatusUpdate(int $userId, string $status, array $additionalData = []): void
    {
        $data = [
            'type' => 'status_update',
            'data' => array_merge([
                'status' => $status,
                'timestamp' => (new \DateTime())->format(\DateTime::ATOM)
            ], $additionalData)
        ];

        $this->publishToUser($userId, $data);
    }

    /**
     * Exemple d'utilisation : Publier le compteur de notifications non lues
     */
    public function publishUnreadCountToUser(int $userId, int $unreadCount): void
    {
        $data = [
            'type' => 'unread_count_update',
            'data' => [
                'userId' => $this->cryptService->encryptId($userId, 'user'),
                'unreadCount' => $unreadCount,
                'updatedAt' => (new \DateTime())->format('c')
            ]
        ];

        $this->publishToUser($userId, $data);
    }

    /**
     * Convertit une entité Notification en DTO
     */
    private function convertToDTO(Notification $notification): NotificationResponseDTO
    {
        return new NotificationResponseDTO(
            id: $this->cryptService->encryptId($notification->getId(), 'notification'),
            titre: $notification->getTitre(),
            message: $notification->getMessage(),
            type: $notification->getType(),
            cible: $notification->getCible(),
            isRead: $notification->isRead(),
            createdAt: $notification->getCreatedAt(),
            userId: $notification->getUser() ? $this->cryptService->encryptId($notification->getUser()->getId(), 'user') : null
        );
    }
}
