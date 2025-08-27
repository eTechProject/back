<?php

namespace App\Controller\Client;

use App\DTO\Client\Request\AlertRequestDTO;
use App\Service\AlertCreator;
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
    private readonly AlertCreator $alertCreator,
    private readonly CryptService $cryptService
    ) {}

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    /**
     * @OA\Post(
     *     path="/api/alerts",
     *     summary="Créer une alerte",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"userId","orderId","type","position"},
     *             @OA\Property(property="userId", type="integer"),
     *             @OA\Property(property="orderId", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"danger","incident","urgence"}),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="position", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alerte créée",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="alertId", type="integer"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Erreur de validation")
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return $this->json(['status' => 'error', 'message' => 'JSON invalide'], 400);
        }
        // Décrypter les IDs AVANT la désérialisation
        try {
            $data['userId'] = (int) $this->cryptService->decryptId($data['userId'], 'user');
            $data['orderId'] = (int) $this->cryptService->decryptId($data['orderId'], 'service_order');
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => 'ID utilisateur ou commande invalide'], 400);
        }
        $dto = $this->serializer->denormalize($data, AlertRequestDTO::class);
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['status' => 'error', 'message' => (string) $errors], 400);
        }
        try {
            $alert = $this->alertCreator->create($dto);
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
