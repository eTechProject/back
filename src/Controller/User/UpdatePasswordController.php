<?php

namespace App\Controller\User;

use App\DTO\User\Request\ChangePasswordDTO;
use App\Service\UserService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users/{id}/password', name: 'api_update_user_password', methods: ['PUT'])]
class UpdatePasswordController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $decryptedId = $this->cryptService->decryptId($id, EntityType::USER->value);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'ID utilisateur invalide'
            ], 400);
        }

        // Vérifier que l'utilisateur connecté ne peut modifier que son propre mot de passe
        $currentUser = $this->getUser();
        if (!$currentUser || $currentUser->getId() !== $decryptedId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Accès refusé : vous ne pouvez modifier que votre propre mot de passe'
            ], 403);
        }

        try {
            $updatePasswordRequest = $this->serializer->deserialize(
                $request->getContent(),
                ChangePasswordDTO::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le format de la requête est invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $this->validator->validate($updatePasswordRequest);
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
            $this->userService->updatePasswordFromRequest($decryptedId, $updatePasswordRequest);

            return $this->json([
                'status' => 'success',
                'message' => 'Mot de passe mis à jour avec succès'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur serveur lors de la mise à jour du mot de passe'
            ], 500);
        }
    }
}
