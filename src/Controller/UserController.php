<?php

namespace App\Controller;

use App\DTO\User\RegisterUserDTO;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class UserController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserService $userService,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $registerRequest = $serializer->deserialize(
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

        $errors = $validator->validate($registerRequest);
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
            $user = $userService->createUserFromRequest($registerRequest);
            $em->persist($user);
            $em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Utilisateur enregistré avec succès'
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
