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
#[Route('/api/admin/clients/{encryptedId}', name: 'api_admin_client_delete', methods: ['DELETE'])]
class DeleteController extends AbstractController
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

            $deleted = $this->userService->deleteUser($clientId);
            if (!$deleted) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Impossible de supprimer le client'
                ], 500);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Client supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du client'
            ], 500);
        }
    }
}
