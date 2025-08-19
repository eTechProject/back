<?php

namespace App\Service;

use App\DTO\Agent\Request\RecordLocationDTO;
use App\DTO\Agent\Response\LocationRecordedDTO;
use App\Entity\AgentLocationsRaw;
use App\Entity\AgentLocationSignificant;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Enum\EntityType;
use App\Enum\Reason;
use App\Enum\Status;
use App\Repository\AgentLocationsRawRepository;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AgentLocationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgentsRepository $agentsRepository,
        private TasksRepository $tasksRepository,
        private AgentLocationsRawRepository $agentLocationsRawRepository,
        private AgentLocationSignificantRepository $agentLocationSignificantRepository,
        private CryptService $cryptService,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private AgentLocationArchiveService $archiveService
    ) {}

    /**
     * Process location recording request with full validation and response creation
     * 
     * @param string $encryptedUserId The encrypted user ID (linked to the agent)
     * @param string $requestContent Raw JSON request content
     * @return array Success response array
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If processing fails
     */
    public function processLocationRequest(string $encryptedUserId, string $requestContent): array
    {
        // 1. Deserialize and validate request
        $recordLocationDTO = $this->deserializeAndValidateRequest($requestContent);

        // 2. Record location
        $rawLocation = $this->recordLocation($encryptedUserId, $recordLocationDTO);

        // 3. Build response DTO
        $responseDTO = LocationRecordedDTO::fromLocationData(
            locationId: $rawLocation->getId(),
            recordedAt: $rawLocation->getRecordedAt(),
            isSignificant: $rawLocation->isSignificant(),
            longitude: $recordLocationDTO->longitude,
            latitude: $recordLocationDTO->latitude,
            accuracy: $rawLocation->getAccuracy(),
            speed: $rawLocation->getSpeed(),
            batteryLevel: $rawLocation->getBatteryLevel(),
            reason: $recordLocationDTO->reason
        );

        // 4. Return success response
        return [
            'status' => 'success',
            'message' => 'Position enregistrée avec succès',
            'data' => $responseDTO,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Deserialize and validate request content
     */
    private function deserializeAndValidateRequest(string $requestContent): RecordLocationDTO
    {
        // Check if content is empty
        if (empty($requestContent)) {
            throw new \InvalidArgumentException('Contenu de la requête vide');
        }

        // Deserialize JSON
        try {
            $recordLocationDTO = $this->serializer->deserialize(
                $requestContent,
                RecordLocationDTO::class,
                'json'
            );
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Format JSON invalide: ' . $e->getMessage());
        }

        // Validate DTO constraints
        $errors = $this->validator->validate($recordLocationDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException('Données invalides: ' . json_encode($errorMessages));
        }

        // Validate credibility
        if (!$this->validateLocationCredibility($recordLocationDTO)) {
            throw new \InvalidArgumentException('Données de localisation non crédibles');
        }

        return $recordLocationDTO;
    }

    /**
     * Record agent location (optimized)
     * 
     * @param string $encryptedUserId The encrypted user ID (linked to the agent)
     * @param RecordLocationDTO $locationData The location data
     * @return AgentLocationsRaw
     * @throws \InvalidArgumentException If agent not found or validation fails
     * @throws \RuntimeException If database operation fails
     */
    public function recordLocation(string $encryptedUserId, RecordLocationDTO $locationData): AgentLocationsRaw
    {
        // Add debug logging to verify method is being called
        $this->logger->info('AgentLocationService::recordLocation called', [
            'encrypted_user_id' => $encryptedUserId,
            'task_id' => $locationData->taskId,
            'is_significant' => $locationData->isSignificant,
            'coordinates' => [$locationData->longitude, $locationData->latitude]
        ]);

        try {
            $this->entityManager->beginTransaction();

            // 1. Get and validate agent
            $agent = $this->getAndValidateAgent($encryptedUserId);

            // 2. Get and validate task from request
            $task = $this->getAndValidateTask($locationData->taskId, $agent);

            // 3. Pre-calculate timestamp once for both entities
            $recordedAt = new \DateTimeImmutable();

            // 4. Create raw location entry
            $rawLocation = $this->createRawLocation($agent, $task, $locationData, $recordedAt);
            
            // 5. If significant, create significant location entry
            $significantLocation = null;
            if ($locationData->isSignificant === true) {
                $significantLocation = $this->createSignificantLocation($agent, $task, $locationData, $rawLocation, $recordedAt);
            }

            // 6. Batch flush for better performance
            $this->entityManager->flush();
            $this->entityManager->commit();

            // 7. Check if this is an end_task event and create archive
            if ($locationData->isSignificant === true && $locationData->reason === 'end_task') {
                $this->createTaskArchiveOnEnd($agent, $task);
            }

            // 8. Publish to Mercure (async-like, doesn't block)
            $this->publishLocationUpdate($agent,$task, $rawLocation, $significantLocation);

            $this->logger->info('Location recorded successfully', [
                'user_id' => $encryptedUserId,
                'task_id' => $this->cryptService->encryptId($task->getId(), EntityType::TASK->value),
                'is_significant' => $locationData->isSignificant,
                'coordinates' => [$locationData->longitude, $locationData->latitude]
            ]);

            return $rawLocation;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to record location', [
                'encrypted_user_id' => $encryptedUserId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get and validate agent from encrypted user ID
     */
    private function getAndValidateAgent(string $encryptedUserId): Agents
    {
        try {
            $userId = $this->cryptService->decryptId($encryptedUserId, EntityType::USER->value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('ID utilisateur invalide: ' . $e->getMessage());
        }

        $agent = $this->agentsRepository->findOneBy(['user' => $userId]);
        if (!$agent) {
            throw new \InvalidArgumentException('Agent non trouvé pour cet utilisateur');
        }

        return $agent;
    }

    /**
     * Get and validate task from encrypted ID and verify it belongs to the agent
     */
    private function getAndValidateTask(string $encryptedTaskId, Agents $agent): Tasks
    {
        try {
            $taskId = $this->cryptService->decryptId($encryptedTaskId, EntityType::TASK->value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('ID de tâche invalide: ' . $e->getMessage());
        }

        $task = $this->tasksRepository->find($taskId);
        if (!$task) {
            throw new \InvalidArgumentException('Tâche non trouvée');
        }

        // Verify the task belongs to the agent
        if ($task->getAgent()->getId() !== $agent->getId()) {
            throw new \InvalidArgumentException('Cette tâche n\'appartient pas à cet agent');
        }

        // Verify the task is active
        if (!in_array($task->getStatus(), [Status::PENDING, Status::IN_PROGRESS])) {
            throw new \InvalidArgumentException('La tâche n\'est pas active');
        }

        return $task;
    }

    /**
     * Create raw location entry (optimized)
     */
    private function createRawLocation(Agents $agent, Tasks $task, RecordLocationDTO $locationData, \DateTimeImmutable $recordedAt = null): AgentLocationsRaw
    {
        $rawLocation = new AgentLocationsRaw();
        
        // Create Point geometry
        $pointWKT = sprintf('POINT(%.6f %.6f)', $locationData->longitude, $locationData->latitude);
        $rawLocation->setGeom($pointWKT);
        
        $rawLocation->setAgent($agent);
        $rawLocation->setTask($task);
        $rawLocation->setRecordedAt($recordedAt ?? new \DateTimeImmutable());
        $rawLocation->setAccuracy($locationData->accuracy);
        $rawLocation->setSpeed($locationData->speed);
        $rawLocation->setBatteryLevel($locationData->batteryLevel);
        $rawLocation->setIsSignificant($locationData->isSignificant);

        $this->entityManager->persist($rawLocation);

        return $rawLocation;
    }

    /**
     * Create significant location entry if needed (optimized)
     */
    private function createSignificantLocation(
        Agents $agent, 
        Tasks $task, 
        RecordLocationDTO $locationData, 
        AgentLocationsRaw $rawLocation,
        \DateTimeImmutable $recordedAt = null
    ): ?AgentLocationSignificant {
        if ($locationData->isSignificant !== true || !$locationData->reason) {
            return null;
        }

        $significantLocation = new AgentLocationSignificant();
        
        // Use same Point geometry as raw location (avoid duplication)
        $significantLocation->setGeom($rawLocation->getGeom());
        $significantLocation->setAgent($agent);
        $significantLocation->setTask($task);
        $significantLocation->setRecordedAt($recordedAt ?? $rawLocation->getRecordedAt());
        
        // Convert reason string to enum
        $reason = Reason::from($locationData->reason);
        $significantLocation->setReason($reason);

        $this->entityManager->persist($significantLocation);

        return $significantLocation;
    }

    /**
     * Publish location update to Mercure (optimized)
     */
    private function publishLocationUpdate(
        Agents $agent,
        Tasks $task, 
        AgentLocationsRaw $rawLocation, 
        ?AgentLocationSignificant $significantLocation
    ): void {
        try {
            // Check if Mercure is configured
            if (empty($this->mercureHub->getUrl())) {
                $this->logger->warning('Mercure Hub non configuré pour la publication de position', [
                    'agent_id' => $agent->getId()
                ]);
                return;
            }

            // Pre-calculate encrypted agent ID once (performance optimization)
            $encryptedAgentId = $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value);
            $encryptedOrderId = $this->cryptService->encryptId($task->getOrder()->getId(), EntityType::SERVICE_ORDER->value);

            // Extract coordinates from geometry once (avoid redundant regex)
            $coordinates = $this->extractCoordinatesFromPoint($rawLocation->getGeom());

            // Prepare payload
            $payload = [
                'agent_id' => $encryptedAgentId,
                'longitude' => $coordinates[0],
                'latitude' => $coordinates[1],
                'accuracy' => $rawLocation->getAccuracy(),
                'speed' => $rawLocation->getSpeed(),
                'battery_level' => $rawLocation->getBatteryLevel(),
                'recorded_at' => $rawLocation->getRecordedAt()->format(\DateTimeInterface::ATOM),
                'is_significant' => $rawLocation->isSignificant(),
                'reason' => $significantLocation?->getReason()->value
            ];

            // Topic: /agents/{encrypted_id}/location
            $topic = sprintf('order/%s/agents/location', $encryptedOrderId);

            // Add retry logic similar to MessageService
            $maxRetries = 3;
            $retryDelay = 500; // milliseconds
            $lastException = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->logger->debug('Tentative de publication Mercure location', [
                        'topic' => $topic,
                        'attempt' => $attempt,
                        'agent_id' => $encryptedAgentId
                    ]);
                    
                    // Publish update (public, like MessageService)
                    $update = new Update($topic, json_encode($payload));
                    $this->mercureHub->publish($update);

                    $this->logger->info('Location update published to Mercure', [
                        'topic' => $topic,
                        'agent_id' => $encryptedAgentId,
                        'attempt' => $attempt,
                        'payload_size' => strlen(json_encode($payload))
                    ]);
                    
                    return; // Success, exit retry loop
                    
                } catch (\Throwable $e) {
                    $lastException = $e;
                    
                    $this->logger->warning('Échec de la tentative de publication Mercure location', [
                        'topic' => $topic,
                        'attempt' => $attempt,
                        'agent_id' => $encryptedAgentId,
                        'error' => $e->getMessage(),
                        'retry_remaining' => ($maxRetries - $attempt)
                    ]);
                    
                    // Si ce n'est pas la dernière tentative, attendre avant de réessayer
                    if ($attempt < $maxRetries) {
                        usleep($retryDelay * 1000); // Conversion en microsecondes
                        
                        // Augmenter progressivement le délai (backoff exponentiel)
                        $retryDelay *= 2;
                    }
                }
            }

            // Toutes les tentatives ont échoué
            $this->logger->error('Échec définitif de la publication Mercure location', [
                'topic' => $topic,
                'agent_id' => $encryptedAgentId,
                'attempts' => $maxRetries,
                'final_error' => $lastException ? $lastException->getMessage() : 'Erreur inconnue'
            ]);

        } catch (\Exception $e) {
            // Don't fail the whole operation if Mercure fails
            $this->logger->error('Failed to publish location update to Mercure', [
                'agent_id' => $agent->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract longitude and latitude from Point WKT
     */
    private function extractCoordinatesFromPoint(string $pointWKT): array
    {
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $pointWKT, $matches)) {
            return [(float)$matches[1], (float)$matches[2]];
        }

        // Return default coordinates if parsing fails
        return [0.0, 0.0];
    }

    /**
     * Validate location data for credibility
     */
    public function validateLocationCredibility(RecordLocationDTO $locationData): bool
    {
        // Basic validation rules for credible location data
        
        // 1. Accuracy should be reasonable (not too precise, not too imprecise)
        if ($locationData->accuracy < 1 || $locationData->accuracy > 2000) {
            return false;
        }

        // 2. Speed should be reasonable for human movement (max 200 km/h)
        if ($locationData->speed !== null && ($locationData->speed < 0 || $locationData->speed > 55.56)) { // 55.56 m/s = 200 km/h
            return false;
        }

        // 3. Battery level should be valid percentage
        if ($locationData->batteryLevel !== null && ($locationData->batteryLevel < 0 || $locationData->batteryLevel > 100)) {
            return false;
        }

        // 4. If marked as significant, reason should be provided
        if ($locationData->isSignificant === true && empty($locationData->reason)) {
            return false;
        }

        return true;
    }

    /**
     * Create task archive when task ends
     */
    private function createTaskArchiveOnEnd(Agents $agent, Tasks $task): void
    {
        try {
            // Check if archive already exists
            if ($this->archiveService->archiveExistsForTask($task)) {
                $this->logger->info('Archive already exists for task', [
                    'agent_id' => $agent->getId(),
                    'task_id' => $task->getId()
                ]);
                return;
            }

            // Create the archive
            $archive = $this->archiveService->createTaskArchive($agent, $task);
            
            if ($archive) {
                $this->logger->info('Task archive created on end_task', [
                    'agent_id' => $agent->getId(),
                    'task_id' => $task->getId(),
                    'archive_id' => $archive->getId()
                ]);
            }

        } catch (\Exception $e) {
            // Don't fail the location recording if archive creation fails
            $this->logger->error('Failed to create task archive on end_task', [
                'agent_id' => $agent->getId(),
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
