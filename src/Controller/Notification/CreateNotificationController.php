<?php

namespace App\Controller\Notification;

use App\DTO\Notification\Request\CreateNotificationDTO;
use App\Service\Notification\NotificationService;
use App\Service\CryptService;
use App\Repository\UserRepository;
use App\Enum\EntityType;
use App\Enum\NotificationTarget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_create', methods: ['POST'])]
#[IsGranted('ROLE_CLIENT')]
class CreateNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CryptService $cryptService,
        private readonly UserRepository $userRepository
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $createRequest = $this->serializer->deserialize(
                $request->getContent(),
                CreateNotificationDTO::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le format de la requête est invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $this->validator->validate($createRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'status' => 'error',
                'message' => 'Échec de la validation',
                'errors' => $errorMessages
            ], 422);
        }

        try {
            $user = null;
            if ($createRequest->userId) {
                // Utilisateur spécifique fourni
                $decryptedUserId = $this->cryptService->decryptId($createRequest->userId, EntityType::USER->value);
                $user = $this->userRepository->find($decryptedUserId);
                
                if (!$user) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Utilisateur non trouvé'
                    ], 404);
                }
            } elseif ($createRequest->cible === NotificationTarget::USER) {
                // Si cible = "user" et pas d'userId spécifique, utiliser l'utilisateur connecté
                $user = $this->getUser();
            }

            $notification = $this->notificationService->createNotification(
                $createRequest->titre,
                $createRequest->message,
                $createRequest->type,
                $createRequest->cible,
                $user
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Notification créée avec succès',
                'data' => [
                    'id' => $this->cryptService->encryptId($notification->getId(), EntityType::NOTIFICATION->value),
                    'titre' => $notification->getTitre(),
                    'message' => $notification->getMessage(),
                    'type' => $notification->getType()->value,
                    'cible' => $notification->getCible()->value,
                    'isRead' => $notification->isRead(),
                    'createdAt' => $notification->getCreatedAt()->format('c')
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de la notification'
            ], 500);
        }
    }
}
