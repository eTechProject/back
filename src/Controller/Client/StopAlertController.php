<?php

namespace App\Controller\Client;

use App\DTO\Client\Request\StopAlertRequestDTO;
use App\Service\CryptService;
use App\Service\Notification\NotificationService;
use App\Service\ServiceOrderService;
use App\Repository\UserRepository;
use App\Repository\ServiceOrdersRepository;
use App\Repository\AlertRepository;
use App\Enum\NotificationType;
use App\Enum\NotificationTarget;
use App\Enum\EntityType;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/alerts/stop', name: 'api_alert_stop', methods: ['POST'])]
class StopAlertController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CryptService $cryptService,
        private readonly ServiceOrderService $serviceOrderService,
        private readonly ServiceOrdersRepository $serviceOrdersRepository,
        private readonly AlertRepository $alertRepository
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return $this->json(['status' => 'error', 'message' => 'JSON invalide'], 400);
        }
        $dto = $this->serializer->deserialize($request->getContent(), StopAlertRequestDTO::class, 'json');
        $errors = $this->validator->validate($dto);
        
        if (count($errors) > 0) {
            return $this->json(['status' => 'error', 'message' => (string) $errors], 400);
        }

        try {
            $alertId = $this->cryptService->decryptId($dto->alertId, EntityType::ALERT->value);
            $alerts = $this->alertRepository->find($alertId);

        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => 'Invalid alert ID'], 400);
        }
        $serviceOrder = $alerts->getOrder();
        if (!$serviceOrder) {
            return $this->json(['status' => 'error', 'message' => 'Service order not found'], 404);
        }
        if (empty($alerts)) {
            return $this->json(['status' => 'error', 'message' => 'No alerts found for this service order'], 404);
        }

        try {
            $alertTimestamp = $alerts->getTimestamp()->format('d/m/Y à H:i:s');
            $this->serviceOrderService->notifyRelatedAgents(
                $serviceOrder,
                'ALERTE RÉSOLUE',
                "L'alerte lancée à {$alertTimestamp} a été résolue",
                NotificationType::ALERT_STOP
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Alert stopped successfully',
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM)
            ]);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'Failed to stop alert: ' . $e->getMessage()], 500);
        }
    }
}
