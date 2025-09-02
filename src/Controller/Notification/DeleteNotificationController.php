<?php

namespace App\Controller\Notification;

use App\Service\Notification\NotificationService;
use App\Service\CryptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
class DeleteNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return $this->json([
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        try {
            $decryptedId = $this->cryptService->decryptId($id, 'notification');
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'ID de notification invalide'
            ], 400);
        }

        try {
            $notification = $this->notificationService->findById($decryptedId);
            
            if (!$notification) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            // Vérifier les permissions
            $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());
            // Cast $currentUser to your User entity class to access getId()
            $userEntity = $currentUser instanceof \App\Entity\User ? $currentUser : null;
            $isOwner = $notification->getUser() && $userEntity && $notification->getUser()->getId() === $userEntity->getId();
            
            if (!$isAdmin && !$isOwner) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Accès refusé : vous ne pouvez supprimer que vos propres notifications'
                ], 403);
            }

            $this->notificationService->deleteNotification($notification);

            return $this->json([
                'status' => 'success',
                'message' => 'Notification supprimée avec succès'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de la notification'
            ], 500);
        }
    }
}
