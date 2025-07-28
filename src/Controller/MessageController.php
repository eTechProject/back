<?php
namespace App\Controller;

use App\DTO\Message\MessageDTO;
use App\Service\MessageService;
use App\Service\CryptService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageController extends AbstractController
{
    public function __construct(
        private MessageService $messageService,
        private CryptService $cryptService
    ) {}

    #[Route('/api/messages', name: 'api_messages_post', methods: ['POST'])]
    public function postMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $message = $this->messageService->createMessage($data);

            // Créer le DTO avec tous les IDs chiffrés
            $messageDTO = new MessageDTO(
                encryptedId: $this->cryptService->encryptId((string)$message->getId()),
                order_id: $this->cryptService->encryptId((string)$message->getOrder()->getId()),
                sender_id: $this->cryptService->encryptId((string)$message->getSender()->getId()),
                receiver_id: $this->cryptService->encryptId((string)$message->getReceiver()->getId()),
                content: $message->getContent(),
                sent_at: $message->getSentAt()->format('Y-m-d H:i:s')
            );

            return $this->json([
                'status' => 'Message envoyé',
                'message' => $messageDTO,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/messages/{orderId}', name: 'api_messages_get', methods: ['GET'])]
    public function getMessages(int $orderId, Request $request): JsonResponse
    {
        try {
            // Paramètres optionnels pour filtrage/pagination
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 20);
            $sortBy = $request->query->get('sort', 'sent_at');
            $sortOrder = $request->query->get('order', 'DESC');
            $senderId = $request->query->get('sender_id');
            $fromDate = $request->query->get('from_date');
            
            // Construire les critères de filtrage
            $criteria = [
                'page' => $page,
                'limit' => $limit,
                'sort' => $sortBy,
                'order' => $sortOrder
            ];
            
            if ($senderId) {
                $criteria['sender_id'] = $senderId;
            }
            
            if ($fromDate) {
                $criteria['from_date'] = $fromDate;
            }
            
            // Récupérer les messages avec pagination et filtrage
            $result = $this->messageService->getMessagesForOrder($orderId, $criteria);
            $messages = $result['messages'] ?? [];
            $totalItems = $result['total'] ?? count($messages);
            $totalPages = ceil($totalItems / $limit);
            
            // Transformer en DTOs
            $messageDTOs = [];
            foreach ($messages as $message) {
                $messageDTOs[] = new MessageDTO(
                    encryptedId: $this->cryptService->encryptId((string)$message['id']),
                    order_id: $this->cryptService->encryptId((string)$message['order']->getId()),
                    sender_id: $this->cryptService->encryptId((string)$message['sender']->getId()),
                    receiver_id: $this->cryptService->encryptId((string)$message['receiver']->getId()),
                    content: $message['content'],
                    sent_at: $message['sent_at']->format('Y-m-d H:i:s')
                );
            }
            
            // Inclure les métadonnées de pagination dans la réponse avec la structure demandée
            return $this->json([
                'data' => $messageDTOs,
                'total' => $totalItems,
                'page' => $page,
                'pages' => $totalPages
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }
}
