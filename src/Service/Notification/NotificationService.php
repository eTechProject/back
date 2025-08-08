<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\NotificationTarget;
use App\Enum\EntityType;
use App\Repository\NotificationRepository;
use App\Service\Notification\NotificationPublisher;
use App\Service\CryptService;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly CryptService $cryptService,
        private readonly ?NotificationPublisher $notificationPublisher = null
    ) {}

    /**
     * Créer une nouvelle notification
     */
    public function createNotification(
        string $titre,
        string $message,
        NotificationType $type = NotificationType::INFO,
        NotificationTarget $cible = NotificationTarget::ALL,
        ?User $user = null
    ): Notification {
        $notification = new Notification();
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setCible($cible);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());
        
        if ($user) {
            $notification->setUser($user);
        }

        // Enregistrer en base
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Publier en temps réel si disponible
        if ($this->notificationPublisher) {
            $this->notificationPublisher->publishNotification($notification);
        }

        return $notification;
    }

    /**
     * Trouve une notification par son ID
     */
    public function findById(int $id): ?Notification
    {
        return $this->notificationRepository->find($id);
    }

    /**
     * Supprime une notification
     */
    public function deleteNotification(Notification $notification): void
    {
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     */
    public function countUnreadNotifications(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user->getId());
    }

    /**
     * Convertit une notification en DTO pour la réponse
     */
    public function toDTO(Notification $notification): array
    {
        return [
            'id' => $this->cryptService->encryptId($notification->getId(), EntityType::NOTIFICATION->value),
            'titre' => $notification->getTitre(),
            'message' => $notification->getMessage(),
            'type' => $notification->getType()->value,
            'cible' => $notification->getCible()->value,
            'isRead' => $notification->isRead(),
            'createdAt' => $notification->getCreatedAt()->format('c'),
            'userId' => $notification->getUser() ? $this->cryptService->encryptId($notification->getUser()->getId(), EntityType::USER->value) : null
        ];
    }
}
