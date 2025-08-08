<?php

namespace App\Controller\Notification;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MarkAllAsReadController extends AbstractController
{
    private NotificationRepository $notificationRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/notifications/mark-all-read', name: 'api_notification_mark_all_read', methods: ['PATCH'])]
    #[IsGranted('ROLE_CLIENT')]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        // Find all unread notifications for the current user
        $unreadNotifications = $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false
        ]);

        if (empty($unreadNotifications)) {
            return new JsonResponse(['message' => 'No unread notifications found'], 200);
        }

        // Mark all as read
        $count = 0;
        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
            $count++;
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'All notifications marked as read successfully',
            'count' => $count
        ]);
    }
}
