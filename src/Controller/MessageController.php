<?php
namespace App\Controller;

use App\Service\MessageHandlerService;
use App\Service\MessageTokenService;
use App\Service\CryptService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\User;

class MessageController extends AbstractController
{
    public function __construct(
        private MessageHandlerService $messageHandler,
        private MessageTokenService $tokenService,
        private CryptService $cryptService
    ) {}

    #[Route('/api/messages', name: 'api_messages_post', methods: ['POST'])]
    public function postMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $orderId = $data['order_id'] ?? null;
        
        if ($orderId) {
            if ($errorResponse = $this->messageHandler->validateAccess($user, $orderId)) {
                return $errorResponse;
            }
        }
        
        return $this->messageHandler->createMessageResponse($data);
    }
    
    #[Route('/api/messages/mercure-token', name: 'api_messages_mercure_token', methods: ['GET'], priority: 10)]
    public function generateMercureToken(): JsonResponse
    {
        return $this->tokenService->generateTokenResponse($this->getUser());
    }

    #[Route('/api/messages/{encryptedOrderId}', name: 'api_messages_get', methods: ['GET'], priority: -10)]
    public function getMessages(string $encryptedOrderId, Request $request): JsonResponse
    {
        try {
            $orderIdInt = (int) $this->cryptService->decryptId($encryptedOrderId);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Identifiant de commande invalide',
                'status' => 'error'
            ], 400);
        }
        
        /** @var User $user */
        $user = $this->getUser();
        
        if ($errorResponse = $this->messageHandler->validateAccess($user, $orderIdInt)) {
            return $errorResponse;
        }
        
        $criteria = $this->messageHandler->buildCriteria($request);
        return $this->messageHandler->getMessagesResponse($orderIdInt, $criteria);
    }
}
