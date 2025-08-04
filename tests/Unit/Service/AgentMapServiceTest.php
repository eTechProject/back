<?php

namespace Tests\Unit\Service;

use App\Service\AgentMapService;
use App\Service\SecuredZoneService;
use App\Service\CryptService;
use App\Repository\TasksRepository;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\AgentLocationsRawRepository;
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
use App\DTO\Agent\Response\AgentMapDataDTO;
use App\DTO\SecuredZone\Response\SecuredZoneDTO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AgentMapServiceTest extends TestCase
{
    private AgentMapService $agentMapService;
    private MockObject|SecuredZoneService $securedZoneService;
    private MockObject|CryptService $cryptService;
    private MockObject|TasksRepository $tasksRepository;
    private MockObject|AgentLocationSignificantRepository $agentLocationSignificantRepository;
    private MockObject|AgentLocationsRawRepository $agentLocationsRawRepository;

    protected function setUp(): void
    {
        $this->securedZoneService = $this->createMock(SecuredZoneService::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->tasksRepository = $this->createMock(TasksRepository::class);
        $this->agentLocationSignificantRepository = $this->createMock(AgentLocationSignificantRepository::class);
        $this->agentLocationsRawRepository = $this->createMock(AgentLocationsRawRepository::class);

        $this->agentMapService = new AgentMapService(
            $this->securedZoneService,
            $this->cryptService,
            $this->tasksRepository,
            $this->agentLocationSignificantRepository,
            $this->agentLocationsRawRepository
        );
    }

    public function testGetAgentMapDataReturnsNullWhenNoInProgressTask(): void
    {
        $agentIdCrypt = 'encrypted_agent_id';
        $agentId = 1;

        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with($agentIdCrypt, EntityType::AGENT->value)
            ->willReturn($agentId);

        $this->tasksRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'agent' => $agentId,
                'status' => Status::IN_PROGRESS
            ])
            ->willReturn(null);

        $result = $this->agentMapService->getAgentMapData($agentIdCrypt);

        $this->assertNull($result);
    }

    public function testGetAgentMapDataReturnsDataForInProgressTask(): void
    {
        // Create entities
        $agentIdCrypt = 'encrypted_agent_id';
        $agentId = 1;
        $agent = $this->createAgent('Agent Name', 'agent@example.com');
        $client = $this->createUser('Client Name', 'client@example.com', UserRole::CLIENT);
        $securedZone = $this->createSecuredZone('Zone Alpha');
        $serviceOrder = $this->createServiceOrder(Status::IN_PROGRESS, $client, $securedZone);
        $task = $this->createTask($serviceOrder, $agent);
        $agentLocation = $this->createAgentLocationSignificant($agent);

        // Setup mocks
        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with($agentIdCrypt, EntityType::AGENT->value)
            ->willReturn($agentId);

        $this->tasksRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'agent' => $agentId,
                'status' => Status::IN_PROGRESS
            ])
            ->willReturn($task);

        $this->setupCryptServiceMocks();
        $this->setupSecuredZoneServiceMock($securedZone);
        $this->setupAgentLocationSignificantRepositoryMock($agent, [$agentLocation]);

        $result = $this->agentMapService->getAgentMapData($agentIdCrypt);

        $this->assertNotNull($result);
        $this->assertInstanceOf(AgentMapDataDTO::class, $result);
        $this->assertCount(1, $result->positionHistory);
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

    private function createAgentLocationSignificant(Agents $agent): AgentLocationSignificant
    {
        $agentLocation = $this->createMock(AgentLocationSignificant::class);
        $agentLocation->method('getAgent')->willReturn($agent);
        $agentLocation->method('getGeom')->willReturn('POINT(2.3494 48.8537)');
        $agentLocation->method('getRecordedAt')->willReturn(new \DateTimeImmutable('2025-07-30T22:15:00+00:00'));
        $agentLocation->method('getReason')->willReturn(Reason::START_TASK);
        
        return $agentLocation;
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

    private function setupAgentLocationSignificantRepositoryMock(Agents $agent, array $locations): void
    {
        $this->agentLocationSignificantRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                ['agent' => $agent],
                ['recorded_at' => 'DESC'],
                20
            )
            ->willReturn($locations);
    }
}
