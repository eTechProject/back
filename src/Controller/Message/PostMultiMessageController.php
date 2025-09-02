<?php

namespace App\Controller\Message;

use App\DTO\Message\MultiMessageRequestDTO;
use App\Service\MultiMessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/messages/multi', name: 'api_messages_multi', methods: ['POST'])]
#[IsGranted('ROLE_CLIENT')]
class PostMultiMessageController extends AbstractController
{
    public function __construct(
        private readonly MultiMessageService $multiMessageService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Validation du contenu JSON
        $jsonContent = $request->getContent();
        if (empty($jsonContent)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Corps de requête vide'
            ], 400);
        }

        try {
            // Désérialisation du DTO
            $dto = $this->serializer->deserialize($jsonContent, MultiMessageRequestDTO::class, 'json');
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Format JSON invalide',
                'details' => $e->getMessage()
            ], 400);
        }

        // Validation du DTO
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Données de requête invalides',
                'errors' => $errorMessages
            ], 422);
        }

        // Traitement de la requête
        return $this->multiMessageService->handleMultiMessageRequest($dto);
    }
}
