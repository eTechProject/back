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

#[Route('/api/messages/{encryptedOrderId}', name: 'api_messages_get', methods: ['GET'])]
class GetMessagesController extends AbstractController
{
    public function __construct(
        private readonly MessageHandlerService $messageHandler,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $encryptedOrderId, Request $request): JsonResponse
    {
        try {
            $orderIdInt = $this->cryptService->decryptId($encryptedOrderId, EntityType::SERVICE_ORDER->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant de commande invalide'
            ], 400);
        }
        
        /** @var User $user */
        $user = $this->getUser();
        
        if ($errorResponse = $this->messageHandler->validateAccess($user, $orderIdInt)) {
            return $errorResponse;
        }
        
        try {
            $criteria = $this->messageHandler->buildCriteria($request);
            return $this->messageHandler->getMessagesResponse($orderIdInt, $criteria);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des messages'
            ], 500);
        }
    }
}
