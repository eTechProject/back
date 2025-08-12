<?php

namespace App\Controller\AdminPack;

use App\DTO\Pack\Request\UpdatePackDTO;
use App\Service\PackService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/pack/{encryptedId}', name: 'api_admin_pack_update', methods: ['PUT'])]
class UpdateController extends AbstractController
{
    public function __construct(
        private PackService $packService,
        private CryptService $cryptService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(string $encryptedId, Request $request): JsonResponse
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
            $updatePackDTO = $this->serializer->deserialize(
                $request->getContent(),
                UpdatePackDTO::class,
                'json'
            );

            $violations = $this->validator->validate($updatePackDTO);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            $pack = $this->packService->updatePack($id, $updatePackDTO);

            if (!$pack) {
                return $this->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            $packDTO = $this->packService->toDTO($pack);

            return $this->json([
                'success' => true,
                'message' => 'Pack mis à jour avec succès',
                'data' => [
                    'pack' => $packDTO
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur de validation : ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du pack : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
