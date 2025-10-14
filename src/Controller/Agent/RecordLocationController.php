<?php

namespace App\Controller\Agent;

use App\Message\RecordLocationMessage;
use App\Service\AgentLocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

/**
 * Record agent location endpoint
 * Note: idcryptuser is the encrypted USER ID, not agent ID. The agent is found via the user relationship.
 */
#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{idcryptuser}/locations', name: 'api_agent_record_location', methods: ['POST'])]
class RecordLocationController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly AgentLocationService $agentLocationService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(string $idcryptuser, Request $request): JsonResponse
    {
        $this->logger->info('Location recording request received', [
            'encrypted_user_id' => $idcryptuser,
            'request_size' => strlen($request->getContent()),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ]);

        try {
            // Create and validate message using service
            $message = $this->agentLocationService->createLocationMessage(
                $idcryptuser,
                $request->getContent()
            );

            // Dispatch message to queue
            $this->messageBus->dispatch($message);

            $this->logger->info('Location recording message dispatched successfully', [
                'encrypted_user_id' => $idcryptuser,
                'task_id' => $message->getTaskId(),
                'is_significant' => $message->getIsSignificant(),
                'coordinates' => [$message->getLongitude(), $message->getLatitude()],
                'message_unique_id' => $message->getUniqueId()
            ]);

            // Return immediate success response (async processing)
            return $this->json([
                'status' => 'accepted',
                'message' => 'Position envoyée pour traitement asynchrone',
                'data' => [
                    'messageId' => $message->getUniqueId(),
                    'coordinates' => [
                        'longitude' => $message->getLongitude(),
                        'latitude' => $message->getLatitude()
                    ],
                    'isSignificant' => $message->getIsSignificant(),
                    'reason' => $message->getReason(),
                    'acceptedAt' => $message->getRequestedAt()->format(\DateTimeInterface::ATOM)
                ],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ], 202); // 202 Accepted - request accepted for processing

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid location recording request', [
                'encrypted_user_id' => $idcryptuser,
                'error' => $e->getMessage(),
                'request_content' => substr($request->getContent(), 0, 500) // Log first 500 chars for debugging
            ]);

            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Failed to dispatch location recording message', [
                'encrypted_user_id' => $idcryptuser,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'envoi de la position pour traitement',
                'error' => $e->getMessage(),
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ], 500);
        }
    }
}
