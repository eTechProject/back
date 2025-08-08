<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\Notification\NotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationReadService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Marque une notification comme lue par son ID
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $notification = $this->notificationRepository->find($notificationId);
            if (!$notification) {
                return false;
            }

            // Vérifier que l'utilisateur peut marquer cette notification comme lue
            if (!$this->canUserMarkAsRead($notification, $userId)) {
                return false;
            }

            $notification->setIsRead(true);
            $this->entityManager->flush();

            // Publier la mise à jour en temps réel
            $this->publishReadUpdate($userId);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to mark notification as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsReadForUser(int $userId): int
    {
        try {
            $count = $this->notificationRepository->markAllAsReadForUser($userId);

            // Publier la mise à jour du compteur
            $this->notificationPublisher->publishUnreadCountToUser($userId, 0);

            return $count;

        } catch (\Exception $e) {
            $this->logger->error('Failed to mark all notifications as read', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Vérifie si un utilisateur peut marquer une notification comme lue
     */
    private function canUserMarkAsRead(Notification $notification, int $userId): bool
    {
        return $notification->getUser() && $notification->getUser()->getId() === $userId;
    }

    /**
     * Publie la mise à jour de lecture en temps réel
     */
    private function publishReadUpdate(int $userId): void
    {
        try {
            $unreadCount = $this->notificationRepository->countUnreadByUser($userId);
            $this->notificationPublisher->publishUnreadCountToUser($userId, $unreadCount);
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish read update', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
