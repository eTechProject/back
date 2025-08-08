<?php

namespace App\Tests\Unit\Service;

use App\DTO\Agent\Request\RecordLocationDTO;
use App\Entity\AgentLocationsRaw;
use App\Entity\AgentLocationSignificant;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Entity\User;
use App\Enum\EntityType;
use App\Enum\Reason;
use App\Enum\Status;
use App\Enum\UserRole;
use App\Repository\AgentLocationsRawRepository;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;
use App\Service\AgentLocationService;
use App\Service\CryptService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AgentLocationServiceTest extends TestCase
{
    private AgentLocationService $agentLocationService;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|AgentsRepository $agentsRepository;
    private MockObject|TasksRepository $tasksRepository;
    private MockObject|AgentLocationsRawRepository $agentLocationsRawRepository;
    private MockObject|AgentLocationSignificantRepository $agentLocationSignificantRepository;
    private MockObject|CryptService $cryptService;
    private MockObject|HubInterface $mercureHub;
    private MockObject|LoggerInterface $logger;
    private MockObject|SerializerInterface $serializer;
    private MockObject|ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->agentsRepository = $this->createMock(AgentsRepository::class);
        $this->tasksRepository = $this->createMock(TasksRepository::class);
        $this->agentLocationsRawRepository = $this->createMock(AgentLocationsRawRepository::class);
        $this->agentLocationSignificantRepository = $this->createMock(AgentLocationSignificantRepository::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->mercureHub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->agentLocationService = new AgentLocationService(
            $this->entityManager,
            $this->agentsRepository,
            $this->tasksRepository,
            $this->agentLocationsRawRepository,
            $this->agentLocationSignificantRepository,
            $this->cryptService,
            $this->mercureHub,
            $this->logger,
            $this->serializer,
            $this->validator
        );
    }

    public function testRecordLocationSuccess(): void
    {
        // Create test data
        $encryptedAgentId = 'encrypted_agent_123';
        $encryptedTaskId = 'encrypted_task_456';
        $agentId = 1;
        $taskId = 1;
        $locationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            speed: 5.0,
            batteryLevel: 85.0,
            isSignificant: false,
            taskId: $encryptedTaskId
        );

        // Create mock entities
        $agent = $this->createMockAgent($agentId);
        $task = $this->createMockTask($taskId, $agent);
        $rawLocation = $this->createMockRawLocation();

        // Setup expectations
        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnMap([
                [$encryptedAgentId, EntityType::AGENT->value, $agentId],
                [$encryptedTaskId, EntityType::TASK->value, $taskId]
            ]);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->with($agentId)
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('find')
            ->with($taskId)
            ->willReturn($task);

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AgentLocationsRaw::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('commit');

        $this->mercureHub
            ->expects($this->once())
            ->method('getUrl')
            ->willReturn('http://mercure-hub');

        // Execute
        $result = $this->agentLocationService->recordLocation($encryptedAgentId, $locationData);

        // Assert
        $this->assertInstanceOf(AgentLocationsRaw::class, $result);
    }

    public function testRecordLocationWithSignificant(): void
    {
        // Create test data
        $encryptedAgentId = 'encrypted_agent_123';
        $encryptedTaskId = 'encrypted_task_456';
        $agentId = 1;
        $taskId = 1;
        $locationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            isSignificant: true,
            reason: 'start_task',
            taskId: $encryptedTaskId
        );

        // Create mock entities
        $agent = $this->createMockAgent($agentId);
        $task = $this->createMockTask($taskId, $agent);

        // Setup expectations
        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnMap([
                [$encryptedAgentId, EntityType::AGENT->value, $agentId],
                [$encryptedTaskId, EntityType::TASK->value, $taskId]
            ]);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($task);

        $this->entityManager
            ->expects($this->exactly(2)) // Raw + Significant
            ->method('persist');

        // Execute
        $result = $this->agentLocationService->recordLocation($encryptedAgentId, $locationData);

        // Assert
        $this->assertInstanceOf(AgentLocationsRaw::class, $result);
    }

    public function testRecordLocationAgentNotFound(): void
    {
        $encryptedAgentId = 'encrypted_agent_123';
        $locationData = new RecordLocationDTO(2.3522, 48.8566, 10.0, taskId: 'encrypted_task_456');

        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->willReturn(999);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent non trouvé');

        $this->agentLocationService->recordLocation($encryptedAgentId, $locationData);
    }

    public function testRecordLocationTaskNotFound(): void
    {
        $encryptedAgentId = 'encrypted_agent_123';
        $encryptedTaskId = 'encrypted_task_456';
        $agentId = 1;
        $taskId = 999;
        $locationData = new RecordLocationDTO(2.3522, 48.8566, 10.0, taskId: $encryptedTaskId);
        $agent = $this->createMockAgent($agentId);

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnMap([
                [$encryptedAgentId, EntityType::AGENT->value, $agentId],
                [$encryptedTaskId, EntityType::TASK->value, $taskId]
            ]);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tâche non trouvée');

        $this->agentLocationService->recordLocation($encryptedAgentId, $locationData);
    }

    public function testValidateLocationCredibilityValid(): void
    {
        $validLocationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            speed: 5.0,
            batteryLevel: 85.0
        );

        $result = $this->agentLocationService->validateLocationCredibility($validLocationData);

        $this->assertTrue($result);
    }

    public function testValidateLocationCredibilityInvalidAccuracy(): void
    {
        $invalidLocationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 2000.0 // Too imprecise
        );

        $result = $this->agentLocationService->validateLocationCredibility($invalidLocationData);

        $this->assertFalse($result);
    }

    public function testValidateLocationCredibilityInvalidSpeed(): void
    {
        $invalidLocationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            speed: 100.0 // Too fast (360 km/h)
        );

        $result = $this->agentLocationService->validateLocationCredibility($invalidLocationData);

        $this->assertFalse($result);
    }

    public function testValidateLocationCredibilitySignificantWithoutReason(): void
    {
        $invalidLocationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            isSignificant: true
            // Missing reason
        );

        $result = $this->agentLocationService->validateLocationCredibility($invalidLocationData);

        $this->assertFalse($result);
    }

    // Helper methods

    private function createMockAgent(int $id): Agents
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn('Test Agent');
        $user->method('getRole')->willReturn(UserRole::AGENT);

        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn($id);
        $agent->method('getUser')->willReturn($user);

        return $agent;
    }

    private function createMockTask(int $id = 1, ?Agents $agent = null): Tasks
    {
        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn($id);
        $task->method('getStatus')->willReturn(Status::IN_PROGRESS);
        
        if ($agent) {
            $task->method('getAgent')->willReturn($agent);
        }

        return $task;
    }

    private function createMockRawLocation(): AgentLocationsRaw
    {
        $rawLocation = $this->createMock(AgentLocationsRaw::class);
        $rawLocation->method('getId')->willReturn(1);
        $rawLocation->method('getRecordedAt')->willReturn(new \DateTimeImmutable());
        $rawLocation->method('getAccuracy')->willReturn(10.0);
        $rawLocation->method('getSpeed')->willReturn(5.0);
        $rawLocation->method('getBatteryLevel')->willReturn(85.0);
        $rawLocation->method('isSignificant')->willReturn(false);
        $rawLocation->method('getGeom')->willReturn('POINT(2.352200 48.856600)');

        return $rawLocation;
    }

    public function testProcessLocationRequestSuccess(): void
    {
        // Create test data
        $encryptedAgentId = 'encrypted_agent_123';
        $requestContent = json_encode([
            'longitude' => 2.3522,
            'latitude' => 48.8566,
            'accuracy' => 10.0,
            'speed' => 5.0,
            'batteryLevel' => 85.0,
            'isSignificant' => false,
            'taskId' => 'encrypted_task_456'
        ]);

        $agentId = 1;
        $taskId = 1;
        $locationData = new RecordLocationDTO(
            longitude: 2.3522,
            latitude: 48.8566,
            accuracy: 10.0,
            speed: 5.0,
            batteryLevel: 85.0,
            isSignificant: false,
            taskId: 'encrypted_task_456'
        );

        // Create mock entities
        $agent = $this->createMockAgent($agentId);
        $task = $this->createMockTask($taskId, $agent);

        // Setup serializer mock
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with($requestContent, RecordLocationDTO::class, 'json')
            ->willReturn($locationData);

        // Setup validator mock (no errors)
        $violations = $this->createMock(ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(0);
        
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        // Setup other mocks
        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnMap([
                [$encryptedAgentId, EntityType::AGENT->value, $agentId],
                ['encrypted_task_456', EntityType::TASK->value, $taskId]
            ]);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($task);

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (AgentLocationsRaw $entity) {
                // Use reflection to set ID on the real entity for testing
                $reflection = new \ReflectionClass($entity);
                $idProperty = $reflection->getProperty('id');
                $idProperty->setAccessible(true);
                $idProperty->setValue($entity, 123);
                
                // Set recorded timestamp
                $recordedAtProperty = $reflection->getProperty('recordedAt');
                $recordedAtProperty->setAccessible(true);
                $recordedAtProperty->setValue($entity, new \DateTimeImmutable());
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('commit');

        $this->mercureHub
            ->expects($this->once())
            ->method('getUrl')
            ->willReturn('http://mercure-hub');

        // Execute
        $result = $this->agentLocationService->processLocationRequest($encryptedAgentId, $requestContent);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Position enregistrée avec succès', $result['message']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }
}
