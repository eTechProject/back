<?php

namespace App\Controller\AdminClient;

use App\Service\UserService;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/clients-search', name: 'api_admin_client_search', methods: ['GET'])]
class SearchController extends AbstractController
{
    public function __construct(
        private UserService $userService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $name = $request->query->get('name');

            if (!$name) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Le paramètre "name" est requis'
                ], 400);
            }

            $clients = $this->userService->searchUsersByRole(UserRole::CLIENT, $name);
            
            $clientDTOs = [];
            foreach ($clients as $client) {
                $clientDTOs[] = $this->userService->toDTO($client);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Recherche effectuée avec succès',
                'data' => $clientDTOs,
                'meta' => [
                    'total' => count($clientDTOs),
                    'search_term' => $name
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la recherche'
            ], 500);
        }
    }
}
