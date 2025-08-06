<?php

namespace App\Controller\Client;

use App\Service\ServiceOrderService;
use App\Service\CryptService;
use App\DTO\ServiceOrder\Request\CreateServiceOrderDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/service-orders', name: 'api_service_orders_')]
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
}
