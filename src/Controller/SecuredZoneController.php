<?php

namespace App\Controller;

use App\DTO\SecuredZone\CreateSecuredZoneDTO;
use App\Service\SecuredZoneService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/secured-zones', name: 'api_secured_zones_')]
class SecuredZoneController extends AbstractController
{
    public function __construct(
        private SecuredZoneService $securedZoneService,
        private CryptService $cryptService
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $createRequest = $serializer->deserialize(
                $request->getContent(),
                CreateSecuredZoneDTO::class,
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
            $securedZone = $this->securedZoneService->createSecuredZoneFromRequest($createRequest);
            $em->persist($securedZone);
            $em->flush();

            $securedZoneDTO = $this->securedZoneService->toDTO($securedZone);

            return $this->json([
                'status' => 'success',
                'message' => 'Zone sécurisée créée avec succès',
                'data' => $securedZoneDTO
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de la zone sécurisée'
            ], 500);
        }
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        try {
            $securedZones = $this->securedZoneService->findAll();
            $securedZoneDTOs = array_map(
                fn($zone) => $this->securedZoneService->toDTO($zone),
                $securedZones
            );

            return $this->json([
                'status' => 'success',
                'data' => $securedZoneDTOs
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des zones sécurisées'
            ], 500);
        }
    }

    #[Route('/{encryptedId}', name: 'get_by_id', methods: ['GET'])]
    public function getById(string $encryptedId): JsonResponse
    {
        try {
            $id = $this->cryptService->decryptId($encryptedId, EntityType::SECURED_ZONE->value);
            $securedZone = $this->securedZoneService->findById($id);

            if (!$securedZone) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Zone sécurisée non trouvée'
                ], 404);
            }

            $securedZoneDTO = $this->securedZoneService->toDTO($securedZone);

            return $this->json([
                'status' => 'success',
                'data' => $securedZoneDTO
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la zone sécurisée'
            ], 500);
        }
    }
}
