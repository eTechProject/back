<?php

namespace App\Controller\User;

use App\DTO\User\Request\RegisterUserDTO;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/register', name: 'api_register', methods: ['POST'])]
class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $registerRequest = $this->serializer->deserialize(
                $request->getContent(),
                RegisterUserDTO::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le format de la requête est invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $this->validator->validate($registerRequest);
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
            $this->userService->createUserFromRequest($registerRequest);

            return $this->json([
                'status' => 'success',
                'message' => 'Utilisateur enregistré avec succès'
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
