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
use Psr\Log\LoggerInterface;

#[Route('/api/messages/multi', name: 'api_messages_multi', methods: ['POST'])]
#[IsGranted('ROLE_CLIENT')]
class PostMultiMessageController extends AbstractController
{
    public function __construct(
        private readonly MultiMessageService $multiMessageService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Handle both JSON and form-data requests
        $contentType = $request->headers->get('Content-Type', '');
        
        if (str_contains($contentType, 'multipart/form-data')) {
            // Form data request (with potential files)
            $data = $request->request->all();
            $files = $request->files->get('files', []);
            
            // Debug logging
            $this->logger->info('Multipart request received for multi-message', [
                'data_keys' => array_keys($data),
                'data_values' => $data,
                'files_count' => count($files),
                'all_files_structure' => array_keys($request->files->all()),
                'content_type' => $contentType
            ]);
            
            if (!empty($files)) {
                foreach ($files as $index => $file) {
                    if ($file) {
                        $this->logger->info("Multi-message file $index details", [
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime' => $file->getMimeType(),
                            'is_valid' => $file->isValid()
                        ]);
                    }
                }
            }

            // Handle different ways JSON data can be sent in multipart requests
            $jsonContent = null;
            
            // Method 1: JSON string in a specific field (data, message, json, payload)
            $jsonContent = $data['data'] ?? $data['message'] ?? $data['json'] ?? $data['payload'] ?? null;
            
            // Method 2: Direct form fields (convert to JSON for DTO deserialization)
            if (!$jsonContent && (isset($data['sender_id']) || isset($data['receiver_ids']) || isset($data['content']) || isset($data['order_id']))) {
                // Handle receiver_ids if it's sent as multiple fields or comma-separated
                $receiverIds = [];
                if (isset($data['receiver_ids'])) {
                    if (is_array($data['receiver_ids'])) {
                        $receiverIds = $data['receiver_ids'];
                    } else if (is_string($data['receiver_ids'])) {
                        // Handle JSON string or comma-separated values
                        $decoded = json_decode($data['receiver_ids'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $receiverIds = $decoded;
                        } else {
                            $receiverIds = array_map('trim', explode(',', $data['receiver_ids']));
                        }
                    }
                } else {
                    // Check for receiver_ids[] format (multiple fields with same name)
                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, 'receiver_ids')) {
                            $receiverIds[] = $value;
                        }
                    }
                }
                
                // Build the data structure expected by DTO
                $formattedData = [
                    'sender_id' => $data['sender_id'] ?? '',
                    'receiver_ids' => $receiverIds,
                    'order_id' => $data['order_id'] ?? '',
                    'content' => $data['content'] ?? ''
                ];
                
                $jsonContent = json_encode($formattedData);
                $this->logger->info('Converted form data to JSON for multi-message DTO', [
                    'original_data' => $data,
                    'formatted_data' => $formattedData,
                    'receiver_ids_count' => count($receiverIds)
                ]);
            }
            
            // If still no valid JSON content found
            if (!$jsonContent) {
                $this->logger->error('No valid JSON data found in multipart request', [
                    'available_keys' => array_keys($data),
                    'data_sample' => array_slice($data, 0, 5, true) // Log first 5 entries for debugging
                ]);
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Données JSON manquantes dans la requête multipart. Envoyez les données dans un champ "data" (JSON) ou utilisez les champs individuels (sender_id, receiver_ids, order_id, content). Champs reçus: ' . implode(', ', array_keys($data))
                ], 400);
            }
        } else {
            // JSON request (backward compatibility)
            $jsonContent = $request->getContent();
            $files = [];
            
            if (empty($jsonContent)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Corps de requête vide'
                ], 400);
            }
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

        // Traitement de la requête avec les fichiers
        return $this->multiMessageService->handleMultiMessageRequest($dto, $files);
    }
}
