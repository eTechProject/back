<?php

namespace App\Controller\Notification;

use App\Repository\NotificationRepository;
use App\Service\CryptService;
use App\DTO\Notification\Response\NotificationResponseDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetUserNotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $onlyUnread = $request->query->getBoolean('unread_only', false);
        
        $userId = $user->getId();
        
        $notifications = $this->notificationRepository->findPaginatedByUser(
            $userId,
            $page,
            $limit,
            $onlyUnread
        );
        
        $total = $this->notificationRepository->countByUser($userId, $onlyUnread);
        $unreadCount = $this->notificationRepository->countUnreadByUser($userId);
        
        $notificationDTOs = array_map(
            fn($notification) => new NotificationResponseDTO(
                id: $this->cryptService->encryptId($notification->getId(), 'notification'),
                titre: $notification->getTitre(),
                message: $notification->getMessage(),
                type: $notification->getType(),
                cible: $notification->getCible(),
                isRead: $notification->isRead(),
                createdAt: $notification->getCreatedAt(),
                userId: $this->cryptService->encryptId($notification->getUser()->getId(), 'user')
            ),
            $notifications
        );
        
        return new JsonResponse([
            'notifications' => $notificationDTOs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit)
            ],
            'unread_count' => $unreadCount
        ]);
    }
}
