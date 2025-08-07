<?php

namespace App\Controller\AdminClient;

use App\Service\UserService;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/clients/{encryptedId}', name: 'api_admin_client_get', methods: ['GET'])]
class GetController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private CryptService $cryptService
    ) {}

    public function __invoke(string $encryptedId): JsonResponse
    {
        try {
            $clientId = $this->cryptService->decryptId($encryptedId, EntityType::USER->value);
            if (!$clientId) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'ID client invalide'
                ], 400);
            }

            $client = $this->userService->getUserById($clientId);
            if (!$client || $client->getRole() !== UserRole::CLIENT) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Client non trouvé'
                ], 404);
            }

            $userDto = $this->userService->toDTOWithOrders($client);

            return $this->json([
                'status' => 'success',
                'data' => $userDto
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du client'
            ], 500);
        }
    }
}