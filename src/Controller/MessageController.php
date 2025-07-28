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

            // Créer le DTO avec ID chiffré
            $messageDTO = new MessageDTO(
                encryptedId: $this->cryptService->encryptId((string)$message->getId()),
                order_id: $message->getOrder()->getId(),
                sender_id: $message->getSender()->getId(),
                receiver_id: $message->getReceiver()->getId(),
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
    public function getMessages(int $orderId): JsonResponse
    {
        try {
            $messages = $this->messageService->getMessagesForOrder($orderId);
            
            // Si $messages est un tableau d'objets Message
            $messageDTOs = [];
            foreach ($messages as $message) {
                $messageDTOs[] = new MessageDTO(
                    encryptedId: $this->cryptService->encryptId((string)$message->getId()),
                    order_id: $message->getOrder()->getId(),
                    sender_id: $message->getSender()->getId(),
                    receiver_id: $message->getReceiver()->getId(),
                    content: $message->getContent(),
                    sent_at: $message->getSentAt()->format('Y-m-d H:i:s')
                );
            }

            return $this->json($messageDTOs);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }
}
