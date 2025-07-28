<?php

namespace App\Controller;

use App\DTO\ServiceOrder\CreateServiceOrderDTO;
use App\Service\ServiceOrderService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/service-orders', name: 'api_service_orders_')]
class ServiceOrderController extends AbstractController
{
    public function __construct(
        private ServiceOrderService $serviceOrderService,
        private CryptService $cryptService
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $createRequest = $serializer->deserialize(
                $request->getContent(),
                CreateServiceOrderDTO::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le format de la requête est invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $validator->validate($createRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'status' => 'error',
                'message' => 'Échec de la validation',
                'errors' => $errorMessages
            ], 422);
        }

        try {
            $serviceOrder = $this->serviceOrderService->createServiceOrderWithTransaction($createRequest);
            $serviceOrderDTO = $this->serviceOrderService->toDTO($serviceOrder);

            return $this->json([
                'status' => 'success',
                'message' => 'Commande de service créée avec succès',
                'data' => $serviceOrderDTO
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de la commande de service'
            ], 500);
        }
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        try {
            $serviceOrders = $this->serviceOrderService->findAll();
            $serviceOrderDTOs = array_map(
                fn($order) => $this->serviceOrderService->toDTO($order),
                $serviceOrders
            );

            return $this->json([
                'status' => 'success',
                'data' => $serviceOrderDTOs
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des commandes de service'
            ], 500);
        }
    }

    #[Route('/{encryptedId}', name: 'get_by_id', methods: ['GET'])]
    public function getById(string $encryptedId): JsonResponse
    {
        try {
            $id = $this->cryptService->decryptId($encryptedId, EntityType::SERVICE_ORDER->value);
            $serviceOrder = $this->serviceOrderService->findById($id);

            if (!$serviceOrder) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Commande de service non trouvée'
                ], 404);
            }

            $serviceOrderDTO = $this->serviceOrderService->toDTO($serviceOrder);

            return $this->json([
                'status' => 'success',
                'data' => $serviceOrderDTO
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la commande de service'
            ], 500);
        }
    }

    #[Route('/client/{clientEncryptedId}', name: 'get_by_client', methods: ['GET'])]
    public function getByClient(string $clientEncryptedId): JsonResponse
    {
        try {
            $clientId = $this->cryptService->decryptId($clientEncryptedId, EntityType::USER->value);
            $serviceOrders = $this->serviceOrderService->findByClientId($clientId);
            
            $serviceOrderDTOs = array_map(
                fn($order) => $this->serviceOrderService->toDTO($order),
                $serviceOrders
            );
            return $this->json([
                'status' => 'success',
                'data' => $serviceOrderDTOs
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des commandes de service du client'
            ], 500);
        }
    }
}
