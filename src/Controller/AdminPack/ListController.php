<?php

namespace App\Controller\AdminPack;

use App\Service\PackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/public/pack', name: 'api_admin_pack_list', methods: ['GET'])]
class ListController extends AbstractController
{
    public function __construct(
        private PackService $packService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
            [$packs, $total] = $this->packService->getPacksPaginated($page, $limit);
            
            $packDTOs = array_map(
                fn($pack) => $this->packService->toDTO($pack),
                $packs
            );
            
            $pages = (int) ceil($total / $limit);

            return $this->json([
                'success' => true,
                'message' => 'Liste des packs récupérée avec succès',
                'data' => $packDTOs,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs : ' . $e->getMessage()
            ], 500);
        }
    }
}
