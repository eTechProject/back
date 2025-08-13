<?php

namespace App\Controller\AdminPack;

use App\DTO\Pack\Request\CreatePackDTO;
use App\Service\PackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/pack', name: 'api_admin_pack_create', methods: ['POST'])]
class CreateController extends AbstractController
{
    public function __construct(
        private PackService $packService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $createPackDTO = $this->serializer->deserialize(
                $request->getContent(),
                CreatePackDTO::class,
                'json'
            );

            $violations = $this->validator->validate($createPackDTO);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }
                
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            $pack = $this->packService->createPackWithTransaction($createPackDTO);
            $this->packService->validatePackBusinessRules($pack);

            $packDTO = $this->packService->toDTO($pack);

            return new JsonResponse([
                'success' => true,
                'message' => 'Pack créé avec succès',
                'data' => [
                    'pack' => $packDTO
                ]
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur de validation : ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la création du pack : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
