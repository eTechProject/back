<?php

namespace App\Controller\AdminClient;

use App\Service\UserService;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/clients', name: 'api_admin_clients_list', methods: ['GET'])]
class ListController extends AbstractController
{
    public function __construct(private UserService $userService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
            
            [$clients, $total] = $this->userService->getUsersPaginatedByRole(UserRole::CLIENT, $page, $limit);
            
            $clientResponses = array_map(function($client) {
                return $this->userService->toDTO($client);
            }, $clients);

            $pages = (int) ceil($total / $limit);

            return $this->json([
                'status' => 'success',
                'data' => $clientResponses,
                'total' => $total,
                'page' => $page,
                'pages' => $pages
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des clients'
            ], 500);
        }
    }
}