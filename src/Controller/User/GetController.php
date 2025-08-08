<?php

namespace App\Controller\User;

use App\Service\UserService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/users/{id}', name: 'api_get_user', methods: ['GET'])]
class GetController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SerializerInterface $serializer,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $decryptedId = $this->cryptService->decryptId($id, EntityType::USER->value);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'ID utilisateur invalide'
            ], 400);
        }

        try {
            $user = $this->userService->getUserByEncryptedId($id);
            $userDTO = $this->userService->toDTO($user);

            return $this->json([
                'status' => 'success',
                'data' => json_decode($this->serializer->serialize($userDTO, 'json'), true)
            ], 200);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Une erreur interne est survenue'
            ], 500);
        }
    }
}
