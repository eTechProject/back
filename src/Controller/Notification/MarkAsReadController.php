<?php

namespace App\Controller\Notification;

use App\Enum\EntityType;
use App\Repository\NotificationRepository;
use App\Service\CryptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications/{id}/mark-read', name: 'api_notification_mark_read', methods: ['PATCH'])]
#[IsGranted('ROLE_CLIENT')]
class MarkAsReadController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        // Decrypt notification ID
        try {
            $decryptedId = $this->cryptService->decryptId($id, EntityType::NOTIFICATION->value);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid notification ID'], 400);
        }

        // Find notification
        $notification = $this->notificationRepository->find($decryptedId);
        if (!$notification) {
            return new JsonResponse(['error' => 'Notification not found'], 404);
        }

        // Check if user owns this notification
        $user = $this->getUser();
        if ($notification->getUser() !== $user) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Check if notification is already read
        if ($notification->isRead()) {
            return new JsonResponse(['error' => 'Notification already marked as read'], 400);
        }

        // Mark as read
        $notification->setIsRead(true);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Notification marked as read successfully',
            'notification' => [
                'id' => $this->cryptService->encryptId($notification->getId(), EntityType::NOTIFICATION->value),
                'content' => $notification->getMessage(),
                'type' => $notification->getType()->value,
                'is_read' => $notification->isRead(),
                'created_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                'user_id' => $this->cryptService->encryptId($notification->getUser()->getId(), EntityType::USER->value)
            ]
        ]);
    }
}
