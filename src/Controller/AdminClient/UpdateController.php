<?php

namespace App\Controller\AdminClient;

use App\DTO\User\Request\UpdateUserDTO;
use App\Service\UserService;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/clients/{encryptedId}', name: 'api_admin_client_update', methods: ['PUT'])]
class UpdateController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private CryptService $cryptService
    ) {}

    public function __invoke(
        string $encryptedId,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
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

            try {
                $dto = $serializer->deserialize($request->getContent(), UpdateUserDTO::class, 'json');
            } catch (\Exception $e) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Format de requête invalide'
                ], 400);
            }

            $errors = $validator->validate($dto);
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

            $updatedClient = $this->userService->updateUser($clientId, $dto);
            if (!$updatedClient) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Impossible de mettre à jour le client'
                ], 500);
            }

            $userDto = $this->userService->toDTO($updatedClient);
            return $this->json([
                'status' => 'success',
                'message' => 'Client mis à jour avec succès',
                'data' => $userDto
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour du client'
            ], 500);
        }
    }
}
