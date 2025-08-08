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
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        
        [$clients, $total] = $this->userService->getUsersPaginatedByRole(UserRole::CLIENT, $page, $limit);
        
        $clientDtos = [];
        foreach ($clients as $client) {
            $clientDtos[] = $this->userService->toDTO($client);
        }
        $pages = (int) ceil($total / $limit);

        return $this->json([
            'status' => 'success',
            'data' => $clientDtos,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}