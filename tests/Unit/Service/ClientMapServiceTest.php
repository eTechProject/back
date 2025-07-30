<?php

namespace Tests\Unit\Service;

use App\Service\ClientMapService;
use App\Service\ServiceOrderService;
use App\Service\SecuredZoneService;
use App\Service\AgentService;
use App\Service\CryptService;
use App\Repository\TasksRepository;
use App\Repository\AgentLocationSignificantRepository;
use App\Entity\ServiceOrders;
use App\Entity\SecuredZones;
use App\Entity\User;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Entity\AgentLocationSignificant;
use App\Enum\Status;
use App\Enum\Reason;
use App\Enum\Genre;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\Client\ClientMapDataDTO;
use App\DTO\SecuredZone\SecuredZoneDTO;
use App\DTO\Agent\AgentResponseDTO;
use App\DTO\User\UserDTO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ClientMapServiceTest extends TestCase
{
    private ClientMapService $clientMapService;
    private MockObject|ServiceOrderService $serviceOrderService;
    private MockObject|SecuredZoneService $securedZoneService;
    private MockObject|AgentService $agentService;
    private MockObject|CryptService $cryptService;
    private MockObject|TasksRepository $tasksRepository;
    private MockObject|AgentLocationSignificantRepository $agentLocationSignificantRepository;

    protected function setUp(): void
    {
        $this->serviceOrderService = $this->createMock(ServiceOrderService::class);
        $this->securedZoneService = $this->createMock(SecuredZoneService::class);
        $this->agentService = $this->createMock(AgentService::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->tasksRepository = $this->createMock(TasksRepository::class);
        $this->agentLocationSignificantRepository = $this->createMock(AgentLocationSignificantRepository::class);

        $this->clientMapService = new ClientMapService(
            $this->serviceOrderService,
            $this->securedZoneService,
            $this->agentService,
            $this->cryptService,
            $this->tasksRepository,
            $this->agentLocationSignificantRepository
        );
    }

    public function testGetClientMapDataReturnsEmptyArrayWhenNoInProgressOrders(): void
    {
        // Create a service order with different status
        $serviceOrder = $this->createServiceOrder(Status::PENDING);

        $this->serviceOrderService
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$serviceOrder]);

        $result = $this->clientMapService->getClientMapData();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetClientMapDataReturnsDataForInProgressOrders(): void
    {
        // Create entities
        $client = $this->createUser('Client Name', 'client@example.com', UserRole::CLIENT);
        $securedZone = $this->createSecuredZone('Zone Alpha');
        $serviceOrder = $this->createServiceOrder(Status::IN_PROGRESS, $client, $securedZone);
        
        $agent = $this->createAgent('Agent Name', 'agent@example.com');
        $task = $this->createTask($serviceOrder, $agent);
        $agentLocation = $this->createAgentLocation($agent, $task);

        // Setup mocks
        $this->serviceOrderService
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$serviceOrder]);

        $this->setupCryptServiceMocks();
        $this->setupSecuredZoneServiceMock($securedZone);
        $this->setupTasksRepositoryMock($serviceOrder, [$task]);
        $this->setupAgentServiceMock($agent);
        $this->setupAgentLocationRepositoryMock($agent, $task, $agentLocation);

        $result = $this->clientMapService->getClientMapData();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ClientMapDataDTO::class, $result[0]);
    }

    public function testGetClientMapDataWithActiveAgent(): void
    {
        // Create entities
        $client = $this->createUser('Client Name', 'client@example.com', UserRole::CLIENT);
        $securedZone = $this->createSecuredZone('Zone Alpha');
        $serviceOrder = $this->createServiceOrder(Status::IN_PROGRESS, $client, $securedZone);
        
        $agent = $this->createAgent('Agent Name', 'agent@example.com');
        $task = $this->createTask($serviceOrder, $agent);
        $agentLocation = $this->createAgentLocation($agent, $task);

        // Setup mocks
        $this->serviceOrderService
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$serviceOrder]);

        $this->setupCryptServiceMocks();
        $this->setupSecuredZoneServiceMock($securedZone);
        $this->setupTasksRepositoryMock($serviceOrder, [$task]);
        $this->setupAgentServiceMock($agent);
        $this->setupAgentLocationRepositoryMock($agent, $task, $agentLocation);

        $result = $this->clientMapService->getClientMapData();

        $assignedAgents = $result[0]->assignedAgents;
        $this->assertCount(1, $assignedAgents);
        $this->assertEquals('actif', $assignedAgents[0]->status);
        $this->assertNotNull($assignedAgents[0]->currentPosition);
        $this->assertEquals(2.3494, $assignedAgents[0]->currentPosition->longitude);
        $this->assertEquals(48.8537, $assignedAgents[0]->currentPosition->latitude);
    }

    public function testGetClientMapDataWithInactiveAgent(): void
    {
        // Create entities
        $client = $this->createUser('Client Name', 'client@example.com', UserRole::CLIENT);
        $securedZone = $this->createSecuredZone('Zone Alpha');
        $serviceOrder = $this->createServiceOrder(Status::IN_PROGRESS, $client, $securedZone);
        
        $agent = $this->createAgent('Agent Name', 'agent@example.com');
        $task = $this->createTask($serviceOrder, $agent);

        // Setup mocks
        $this->serviceOrderService
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$serviceOrder]);

        $this->setupCryptServiceMocks();
        $this->setupSecuredZoneServiceMock($securedZone);
        $this->setupTasksRepositoryMock($serviceOrder, [$task]);
        $this->setupAgentServiceMock($agent);
        $this->setupAgentLocationRepositoryMock($agent, $task, null); // No location = inactive

        $result = $this->clientMapService->getClientMapData();

        $assignedAgents = $result[0]->assignedAgents;
        $this->assertCount(1, $assignedAgents);
        $this->assertEquals('inactif', $assignedAgents[0]->status);
        $this->assertNull($assignedAgents[0]->currentPosition);
    }

    public function testGetClientMapDataWithMultipleAgents(): void
    {
        // Create entities
        $client = $this->createUser('Client Name', 'client@example.com', UserRole::CLIENT);
        $securedZone = $this->createSecuredZone('Zone Alpha');
        $serviceOrder = $this->createServiceOrder(Status::IN_PROGRESS, $client, $securedZone);
        
        $agent1 = $this->createAgent('Agent One', 'agent1@example.com');
        $agent2 = $this->createAgent('Agent Two', 'agent2@example.com');
        $task1 = $this->createTask($serviceOrder, $agent1, 1);
        $task2 = $this->createTask($serviceOrder, $agent2, 2);
        $agentLocation1 = $this->createAgentLocation($agent1, $task1);

        // Setup mocks
        $this->serviceOrderService
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$serviceOrder]);

        $this->setupCryptServiceMocks();
        $this->setupSecuredZoneServiceMock($securedZone);
        $this->setupTasksRepositoryMock($serviceOrder, [$task1, $task2]);
        
        // Setup agent service for both agents
        $this->agentService
            ->expects($this->exactly(2))
            ->method('getAgentProfile')
            ->willReturnCallback(function($agent) {
                return $this->createAgentResponseDTO($agent);
            });

        // Setup agent locations
        $this->agentLocationSignificantRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [
                    ['agent' => $agent1, 'task' => $task1, 'reason' => Reason::START_TASK],
                    $agentLocation1
                ],
                [
                    ['agent' => $agent2, 'task' => $task2, 'reason' => Reason::START_TASK],
                    null
                ]
            ]);

        $result = $this->clientMapService->getClientMapData();

        $assignedAgents = $result[0]->assignedAgents;
        $this->assertCount(2, $assignedAgents);
        $this->assertEquals('actif', $assignedAgents[0]->status);
        $this->assertEquals('inactif', $assignedAgents[1]->status);
    }

    // Helper methods for creating test entities

    private function createUser(string $name, string $email, UserRole $role): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn($name);
        $user->method('getEmail')->willReturn($email);
        $user->method('getRole')->willReturn($role);
        return $user;
    }

    private function createSecuredZone(string $name): SecuredZones
    {
        $securedZone = $this->createMock(SecuredZones::class);
        $securedZone->method('getId')->willReturn(1);
        $securedZone->method('getName')->willReturn($name);
        $securedZone->method('getGeom')->willReturn('POLYGON((2.3488 48.8534, 2.3500 48.8534, 2.3500 48.8540, 2.3488 48.8540, 2.3488 48.8534))');
        $securedZone->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2025-07-30T10:00:00+00:00'));
        return $securedZone;
    }

    private function createServiceOrder(Status $status, ?User $client = null, ?SecuredZones $securedZone = null): ServiceOrders
    {
        $serviceOrder = $this->createMock(ServiceOrders::class);
        $serviceOrder->method('getId')->willReturn(1);
        $serviceOrder->method('getStatus')->willReturn($status);
        $serviceOrder->method('getDescription')->willReturn('Test service order');
        $serviceOrder->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2025-07-30T09:00:00+00:00'));
        
        if ($client) {
            $serviceOrder->method('getClient')->willReturn($client);
        }
        
        if ($securedZone) {
            $serviceOrder->method('getSecuredZone')->willReturn($securedZone);
        }
        
        return $serviceOrder;
    }

    private function createAgent(string $name, string $email): Agents
    {
        $user = $this->createUser($name, $email, UserRole::AGENT);
        
        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn(1);
        $agent->method('getUser')->willReturn($user);
        $agent->method('getAddress')->willReturn('123 Test Street');
        $agent->method('getSexe')->willReturn(Genre::M);
        $agent->method('getProfilePictureUrl')->willReturn('https://example.com/agent.jpg');
        
        return $agent;
    }

    private function createTask(ServiceOrders $serviceOrder, Agents $agent, int $id = 1): Tasks
    {
        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn($id);
        $task->method('getOrder')->willReturn($serviceOrder);
        $task->method('getAgent')->willReturn($agent);
        $task->method('getStatus')->willReturn(Status::IN_PROGRESS);
        $task->method('getDescription')->willReturn('Test task');
        $task->method('getStartDate')->willReturn(new \DateTimeImmutable('2025-07-30T22:00:00+00:00'));
        $task->method('getEndDate')->willReturn(null);
        $task->method('getAssignPosition')->willReturn('POINT(2.3494 48.8537)');
        
        return $task;
    }

    private function createAgentLocation(Agents $agent, Tasks $task): AgentLocationSignificant
    {
        $agentLocation = $this->createMock(AgentLocationSignificant::class);
        $agentLocation->method('getAgent')->willReturn($agent);
        $agentLocation->method('getTask')->willReturn($task);
        $agentLocation->method('getReason')->willReturn(Reason::START_TASK);
        $agentLocation->method('getGeom')->willReturn('POINT(2.3494 48.8537)');
        $agentLocation->method('getRecordedAt')->willReturn(new \DateTimeImmutable('2025-07-30T22:15:00+00:00'));
        
        return $agentLocation;
    }

    private function createAgentResponseDTO(Agents $agent): AgentResponseDTO
    {
        $userDTO = new UserDTO(
            'encrypted_user_id',
            $agent->getUser()->getEmail(),
            $agent->getUser()->getName(),
            $agent->getUser()->getRole()
        );

        return new AgentResponseDTO(
            'encrypted_agent_id',
            $agent->getAddress(),
            $agent->getSexe(),
            $agent->getProfilePictureUrl(),
            $userDTO
        );
    }

    // Setup helper methods for mocks

    private function setupCryptServiceMocks(): void
    {
        $this->cryptService
            ->expects($this->atLeastOnce())
            ->method('encryptId')
            ->willReturnCallback(function($id, $type) {
                return "encrypted_{$type}_id_{$id}";
            });
    }

    private function setupSecuredZoneServiceMock(SecuredZones $securedZone): void
    {
        $securedZoneDTO = new SecuredZoneDTO(
            'encrypted_secured_zone_id',
            $securedZone->getName(),
            [[2.3488, 48.8534], [2.3500, 48.8534], [2.3500, 48.8540], [2.3488, 48.8540], [2.3488, 48.8534]],
            $securedZone->getCreatedAt()
        );

        $this->securedZoneService
            ->expects($this->once())
            ->method('toDTO')
            ->with($securedZone)
            ->willReturn($securedZoneDTO);
    }

    private function setupTasksRepositoryMock(ServiceOrders $serviceOrder, array $tasks): void
    {
        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['order' => $serviceOrder])
            ->willReturn($tasks);
    }

    private function setupAgentServiceMock(Agents $agent): void
    {
        $agentResponseDTO = $this->createAgentResponseDTO($agent);

        $this->agentService
            ->expects($this->once())
            ->method('getAgentProfile')
            ->with($agent)
            ->willReturn($agentResponseDTO);
    }

    private function setupAgentLocationRepositoryMock(Agents $agent, Tasks $task, ?AgentLocationSignificant $agentLocation): void
    {
        $this->agentLocationSignificantRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'agent' => $agent,
                'task' => $task,
                'reason' => Reason::START_TASK
            ])
            ->willReturn($agentLocation);
    }
}
