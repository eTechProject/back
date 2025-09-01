<?php

namespace App\Controller\Client;

use App\DTO\Client\Request\AlertRequestDTO;
use App\Service\AlertService;
use App\Service\CryptService;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/alerts', name: 'api_alert_create', methods: ['POST'])]
class AlertController extends AbstractController
{
    public function __construct(
    private readonly SerializerInterface $serializer,
    private readonly ValidatorInterface $validator,
    private readonly AlertService $alertCreator,
    private readonly CryptService $cryptService
    ) {}

    #[IsGranted('IS_AUTHENTICATED_FULLY')]

public function __invoke(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    if ($data === null) {
        return $this->json(['status' => 'error', 'message' => 'JSON invalide'], 400);
    }
    $dto = $this->serializer->deserialize($request->getContent(), AlertRequestDTO::class, 'json');
    $errors = $this->validator->validate($dto);
    if (count($errors) > 0) {
        return $this->json(['status' => 'error', 'message' => (string) $errors], 400);
    }
    try {
        $alert = $this->alertCreator->create($dto); // Le service va chercher la dernière commande du user
    } catch (\InvalidArgumentException $e) {
        return $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
    }
    return $this->json([
        'status' => 'success',
        'alertId' => $this->cryptService->encryptId((string)$alert->getId(), 'ALERT'),
        'timestamp' => $alert->getTimestamp()->format(DATE_ATOM)
    ]);
}
    
}
