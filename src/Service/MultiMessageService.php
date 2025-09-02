<?php

namespace App\Service;

use App\DTO\Message\MultiMessageRequestDTO;
use App\DTO\Message\MultiMessageResponseDTO;
use App\Service\MessageService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class MultiMessageService
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly CryptService $cryptService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Traite une requête de message multiple et retourne la réponse
     */
    public function handleMultiMessageRequest(MultiMessageRequestDTO $dto): JsonResponse
    {
        try {
            // Déchiffrer les IDs
            $decryptedData = $this->decryptRequestData($dto);
            
            // Traiter l'envoi multiple
            $result = $this->messageService->createMultipleMessages($decryptedData);
            
            // Créer la réponse
            $responseDTO = new MultiMessageResponseDTO(
                total_sent: $result['total_sent'],
                total_failed: $result['total_failed'],
                successful_conversations: $result['successful_conversations'],
                failed_conversations: $result['failed_conversations'],
                message: $this->generateResponseMessage($result)
            );

            return new JsonResponse([
                'status' => 'success',
                'data' => [
                    'total_sent' => $responseDTO->total_sent,
                    'total_failed' => $responseDTO->total_failed,
                    'successful_conversations' => $responseDTO->successful_conversations,
                    'failed_conversations' => $responseDTO->failed_conversations
                ],
                'message' => $responseDTO->message
            ], 200);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Erreur de validation multi-message', [
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement multi-message', [
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Une erreur inattendue s\'est produite lors de l\'envoi des messages'
            ], 500);
        }
    }

    /**
     * Déchiffre les données de la requête
     */
    private function decryptRequestData(MultiMessageRequestDTO $dto): array
    {
        try {
            $senderId = $this->cryptService->decryptId($dto->sender_id, EntityType::USER->value);
            $orderId = $this->cryptService->decryptId($dto->order_id, EntityType::SERVICE_ORDER->value);
            
            $receiverIds = [];
            foreach ($dto->receiver_ids as $encryptedReceiverId) {
                $receiverIds[] = $this->cryptService->decryptId($encryptedReceiverId, EntityType::USER->value);
            }

            return [
                'sender_id' => $senderId,
                'receiver_ids' => $receiverIds,
                'order_id' => $orderId,
                'content' => $dto->content
            ];

        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Un ou plusieurs identifiants sont invalides');
        }
    }

    /**
     * Génère le message de réponse approprié
     */
    private function generateResponseMessage(array $result): string
    {
        $totalSent = $result['total_sent'];
        $totalFailed = $result['total_failed'];
        $total = $totalSent + $totalFailed;

        if ($totalFailed === 0) {
            return sprintf(
                'Message envoyé avec succès à %d agent%s',
                $totalSent,
                $totalSent > 1 ? 's' : ''
            );
        }

        if ($totalSent === 0) {
            return sprintf(
                'Échec de l\'envoi du message à %d agent%s',
                $totalFailed,
                $totalFailed > 1 ? 's' : ''
            );
        }

        return sprintf(
            'Message envoyé à %d/%d agents (%d succès, %d échecs)',
            $totalSent,
            $total,
            $totalSent,
            $totalFailed
        );
    }
}
