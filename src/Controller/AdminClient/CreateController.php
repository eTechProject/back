<?php

namespace App\Controller\AdminClient;

use App\DTO\Client\Request\CreateClientDTO;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/clients', name: 'api_admin_client_create', methods: ['POST'])]
class CreateController extends AbstractController
{
    public function __construct(private UserService $userService) {}

    public function __invoke(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        try {
            $dto = $serializer->deserialize($request->getContent(), CreateClientDTO::class, 'json');
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

        try {
            $client = $this->userService->createClientFromRequest($dto);
            $userDto = $this->userService->toDTO($client);

            return $this->json([
                'status' => 'success',
                'message' => 'Client créé avec succès',
                'data' => $userDto
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création du client'
            ], 500);
        }
    }
}