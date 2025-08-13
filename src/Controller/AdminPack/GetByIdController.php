<?php

namespace App\Controller\AdminPack;

use App\Service\PackService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/public/pack/{encryptedId}', name: 'api_admin_pack_get_by_id', methods: ['GET'])]
class GetByIdController extends AbstractController
{
    public function __construct(
        private PackService $packService,
        private CryptService $cryptService
    ) {}

    public function __invoke(string $encryptedId): JsonResponse
    {
        try {
            $id = $this->cryptService->decryptId($encryptedId, EntityType::PACK->value);
        } catch (\Exception) {
            return $this->json([
                'success' => false,
                'message' => 'ID invalide'
            ], 400);
        }

        try {
            $pack = $this->packService->findById($id);

            if (!$pack) {
                return $this->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            $packDTO = $this->packService->toDTO($pack);

            return $this->json([
                'success' => true,
                'message' => 'Pack récupéré avec succès',
                'data' =>  $packDTO,
            
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du pack : ' . $e->getMessage()
            ], 500);
        }
    }
}
