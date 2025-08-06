<?php

namespace App\Controller\Message;

use App\Service\MessageHandlerService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\User;

#[Route('/api/messages', name: 'api_messages_post', methods: ['POST'])]
class PostMessageController extends AbstractController
{
    public function __construct(
        private readonly MessageHandlerService $messageHandler,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json([
                'status' => 'error',
                'message' => 'JSON invalide'
            ], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Déchiffrer l'order_id
        $orderIdEncrypted = $data['order_id'] ?? null;
        if ($orderIdEncrypted) {
            try {
                $orderId = $this->cryptService->decryptId($orderIdEncrypted, EntityType::SERVICE_ORDER->value);
            } catch (\Exception) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Identifiant de commande invalide'
                ], 400);
            }
            
            if ($errorResponse = $this->messageHandler->validateAccess($user, $orderId)) {
                return $errorResponse;
            }
            $data['order_id'] = $orderId;
        } else {
            return $this->json([
                'status' => 'error',
                'message' => 'order_id manquant'
            ], 400);
        }

        // Déchiffrer le sender_id
        $senderIdEncrypted = $data['sender_id'] ?? null;
        if ($senderIdEncrypted) {
            try {
                $senderId = $this->cryptService->decryptId($senderIdEncrypted, EntityType::USER->value);
            } catch (\Exception) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Identifiant de l\'expéditeur invalide'
                ], 400);
            }
            $data['sender_id'] = $senderId;
        } else {
            return $this->json([
                'status' => 'error',
                'message' => 'sender_id manquant'
            ], 400);
        }

        // Déchiffrer le receiver_id
        $receiverIdEncrypted = $data['receiver_id'] ?? null;
        if ($receiverIdEncrypted) {
            try {
                $receiverId = $this->cryptService->decryptId($receiverIdEncrypted, EntityType::USER->value);
            } catch (\Exception) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Identifiant du destinataire invalide'
                ], 400);
            }
            $data['receiver_id'] = $receiverId;
        } else {
            return $this->json([
                'status' => 'error',
                'message' => 'receiver_id manquant'
            ], 400);
        }

        try {
            return $this->messageHandler->createMessageResponse($data);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création du message'
            ], 500);
        }
    }
}
