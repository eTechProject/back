<?php

namespace App\Service;

use App\DTO\Message\MessageDTO;
use App\Entity\User;
use App\Service\CryptService;
use App\Service\MessageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MessageHandlerService
{
    public function __construct(
        private MessageService $messageService,
        private CryptService $cryptService,
        private LoggerInterface $logger
    ) {}

    public function validateAccess(?User $user, int $orderId): ?JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié',
                'status' => 'error'
            ], 401);
        }

        if (!$this->messageService->userHasAccessToOrder($orderId, $user->getId(), $user->getRole()->value)) {
            $this->logger->warning('Tentative d\'accès non autorisé', [
                'user_id' => $user->getId(),
                'role' => $user->getRole()->value,
                'order_id' => $orderId
            ]);
            
            return new JsonResponse([
                'error' => 'Vous n\'êtes pas autorisé à accéder à cette commande',
                'status' => 'error'
            ], 403);
        }

        return null;
    }

    public function createMessageResponse(array $data): JsonResponse
    {
        try {
            $message = $this->messageService->createMessage($data);
            $messageDTO = $this->createMessageDTO($message);

            return new JsonResponse([
                'data' => $messageDTO,
                'status' => 'success',
                'message' => 'Message envoyé avec succès'
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function getMessagesResponse(int $orderId, array $criteria): JsonResponse
    {
        try {
            $result = $this->messageService->getMessagesForOrder($orderId, $criteria);
            $messages = $result['messages'] ?? [];
            $totalItems = $result['total'] ?? count($messages);
            $totalPages = ceil($totalItems / $criteria['limit']);

            $messageDTOs = array_map([$this, 'createMessageDTOFromArray'], $messages);
            
            return new JsonResponse([
                'data' => $messageDTOs,
                'total' => $totalItems,
                'page' => $criteria['page'],
                'pages' => $totalPages,
                'status' => 'success'
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Une erreur inattendue s\'est produite',
                'status' => 'error'
            ], 500);
        }
    }

    public function buildCriteria(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        
        $allowedSortFields = ['sent_at', 'content', 'sender_id', 'receiver_id'];
        $sortBy = in_array($request->query->get('sort'), $allowedSortFields) 
            ? $request->query->get('sort') 
            : 'sent_at';
        
        $sortOrder = strtoupper($request->query->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        
        $criteria = [
            'page' => $page,
            'limit' => $limit,
            'sort' => $sortBy,
            'order' => $sortOrder
        ];
        
        if ($senderId = $request->query->get('sender_id')) {
            $criteria['sender_id'] = $senderId;
        }
        
        if ($fromDate = $request->query->get('from_date')) {
            try {
                new \DateTime($fromDate);
                $criteria['from_date'] = $fromDate;
            } catch (\Exception $e) {
                // Ignorer la date invalide
            }
        }
        
        return $criteria;
    }

    private function createMessageDTO($message): MessageDTO
    {
        return new MessageDTO(
            encryptedId: $this->cryptService->encryptId((string)$message->getId()),
            order_id: $this->cryptService->encryptId((string)$message->getOrder()->getId()),
            sender_id: $this->cryptService->encryptId((string)$message->getSender()->getId()),
            receiver_id: $this->cryptService->encryptId((string)$message->getReceiver()->getId()),
            content: $message->getContent(),
            sent_at: $message->getSentAt()->format('Y-m-d H:i:s')
        );
    }

    private function createMessageDTOFromArray(array $message): MessageDTO
    {
        return new MessageDTO(
            encryptedId: $this->cryptService->encryptId((string)$message['id']),
            order_id: $this->cryptService->encryptId((string)$message['order']->getId()),
            sender_id: $this->cryptService->encryptId((string)$message['sender']->getId()),
            receiver_id: $this->cryptService->encryptId((string)$message['receiver']->getId()),
            content: $message['content'],
            sent_at: $message['sent_at']->format('Y-m-d H:i:s')
        );
    }
}
