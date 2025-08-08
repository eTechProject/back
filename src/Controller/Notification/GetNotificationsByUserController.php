<?php

namespace App\Controller\Notification;

use App\Enum\EntityType;
use App\Repository\NotificationRepository;
use App\Service\CryptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users/{userId}/notifications', name: 'api_get_notifications_by_user_id', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetNotificationsByUserController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private CryptService $cryptService
    ) {
    }

    public function __invoke(string $userId, Request $request): JsonResponse
    {
        // Decrypt user ID
        try {
            $decryptedUserId = $this->cryptService->decryptId($userId, 'user');
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid user ID'], 400);
        }

        // Verify that user can only access their own notifications
        $currentUser = $this->getUser();
        if (!$currentUser || $currentUser->getId() !== $decryptedUserId) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Get pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        // Get user notifications
        $notifications = $this->notificationRepository->findPaginatedByUser(
            $decryptedUserId,
            $page,
            $limit
        );

        $total = $this->notificationRepository->countByUser($decryptedUserId);

        return new JsonResponse([
            'notifications' => array_map(fn($notification) => [
                'id' => $this->cryptService->encryptId($notification->getId(), EntityType::NOTIFICATION->value),
                'title' => $notification->getTitre(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s')
            ], $notifications),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]);
    }
}
