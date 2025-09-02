<?php
namespace App\Controller\Notification;

use App\Enum\EntityType;
use App\Repository\NotificationRepository;
use App\Service\CryptService;
use App\DTO\Notification\Response\NotificationResponseDTO;
use App\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\Notification\NotificationService;

#[Route('/api/users/{userId}/notifications', name: 'api_get_notifications_by_user_id', methods: ['GET'])]
class GetNotificationsByUserController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private CryptService $cryptService,
        private NotificationService $notificationService
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
        // Assuming your User entity implements getId(), otherwise use getUserIdentifier()
        $currentUserId = method_exists($currentUser, 'getId') ? $currentUser->getId() : $currentUser?->getUserIdentifier();
        if (!$currentUser || $currentUserId != $decryptedUserId) {
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
            'status' => 'success',
            'data' => array_map(function (Notification $notification) {
                return $this->notificationService->toDTO($notification);
            }, $notifications),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]);
    }
}
