<?php

namespace App\Controller\Message;

use App\Service\MessageHandlerService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/messages/conversation', name: 'api_messages_conversation', methods: ['GET'])]
class GetConversationMessagesController extends AbstractController
{
    public function __construct(
        private readonly MessageHandlerService $messageHandler,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $senderIdEncrypted = $request->query->get('sender_id');
        $receiverIdEncrypted = $request->query->get('receiver_id');
        
        if (!$senderIdEncrypted || !$receiverIdEncrypted) {
            return $this->json([
                'status' => 'error',
                'message' => 'sender_id ou receiver_id manquant'
            ], 400);
        }

        try {
            $senderId = $this->cryptService->decryptId($senderIdEncrypted, EntityType::USER->value);
            $receiverId = $this->cryptService->decryptId($receiverIdEncrypted, EntityType::AGENT->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant utilisateur invalide'
            ], 400);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 20));

        try {
            $messagesData = $this->messageHandler->getConversationMessages($senderId, $receiverId, $page, $limit);

            return $this->json([
                'status' => 'success',
                'data' => $messagesData['messages'],
                'total' => $messagesData['total'],
                'page' => $page,
                'pages' => (int) ceil($messagesData['total'] / $limit),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la conversation'
            ], 500);
        }
    }
}
