<?php

namespace App\Controller\Notification;

use App\Service\Notification\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetUnreadNotificationsCountController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function __invoke(): JsonResponse
    {
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return $this->json([
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        try {
            $unreadCount = $this->notificationService->countUnreadNotifications($currentUser);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'unreadCount' => $unreadCount
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du nombre de notifications non lues'
            ], 500);
        }
    }
}
