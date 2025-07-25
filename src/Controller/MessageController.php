<?php
namespace App\Controller;

use App\Service\MessageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageController extends AbstractController
{
    public function __construct(private MessageService $messageService) {}

    #[Route('/api/public/messages', name: 'api_messages_post', methods: ['POST'])]
    public function postMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $message = $this->messageService->createMessage($data);

            return $this->json([
                'status' => 'Message envoyé',
                'message' => [
                    'id' => $message->getId(),
                    'order_id' => $message->getOrder()->getId(),
                    'sender_id' => $message->getSender()->getId(),
                    'receiver_id' => $message->getReceiver()->getId(),
                    'content' => $message->getContent(),
                    'sent_at' => $message->getSentAt()->format('Y-m-d H:i:s'),
                ],
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

            return $this->json($messages);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }
}
