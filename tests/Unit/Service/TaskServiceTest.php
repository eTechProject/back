<?php

namespace Tests\Unit\Service;

use App\Service\TaskService;
use App\Service\CryptService;
use App\Repository\TasksRepository;
use App\Repository\ServiceOrdersRepository;
use App\Repository\AgentsRepository;
use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\Agents;
use App\Entity\User;
use App\Enum\Status;
use App\Enum\EntityType;
use App\Enum\Genre;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TaskServiceTest extends TestCase
{
    private TaskService $taskService;
    private MockObject|TasksRepository $tasksRepository;
    private MockObject|ServiceOrdersRepository $serviceOrdersRepository;
    private MockObject|AgentsRepository $agentsRepository;
    private MockObject|CryptService $cryptService;
    private MockObject|EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->tasksRepository = $this->createMock(TasksRepository::class);
        $this->serviceOrdersRepository = $this->createMock(ServiceOrdersRepository::class);
        $this->agentsRepository = $this->createMock(AgentsRepository::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->taskService = new TaskService(
            $this->tasksRepository,
            $this->serviceOrdersRepository,
            $this->agentsRepository,
            $this->cryptService,
            $this->entityManager
        );
    }

    public function testAssignAgentsToOrderSuccess(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1',
                'coordinates' => [2.3522, 48.8566]
            ],
            [
                'agentId' => 'encrypted_agent_2',
                'coordinates' => [2.3542, 48.8576]
            ]
        ];

        // Mock service order
        $serviceOrder = $this->createMockServiceOrder();
        
        // Mock agents
        $agent1 = $this->createMockAgent(1, 'Agent One');
        $agent2 = $this->createMockAgent(2, 'Agent Two');

        // Setup crypto service expectations
        $this->cryptService
            ->expects($this->exactly(3))
            ->method('decryptId')
            ->willReturnCallback(function($encryptedId, $entityType) {
                if ($encryptedId === 'encrypted_order_123' && $entityType === EntityType::SERVICE_ORDER->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_1' && $entityType === EntityType::AGENT->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_2' && $entityType === EntityType::AGENT->value) {
                    return 2;
                }
                return null;
            });

        // Setup repository expectations
        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($serviceOrder);

        $this->agentsRepository
            ->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function($id) use ($agent1, $agent2) {
                return $id === 1 ? $agent1 : $agent2;
            });

        // Mock agent availability (no active tasks)
        $this->tasksRepository
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturn([]);

        // Setup entity manager expectations
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(Tasks::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('commit');

        $result = $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Tasks::class, $result);
    }

    public function testAssignAgentsToOrderThrowsExceptionWhenServiceOrderNotFound(): void
    {
        $encryptedOrderId = 'encrypted_order_invalid';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1',
                'coordinates' => [2.3522, 48.8566]
            ]
        ];

        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with('encrypted_order_invalid', EntityType::SERVICE_ORDER->value)
            ->willReturn(999);

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ordre de service non trouvé');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testAssignAgentsToOrderThrowsExceptionWhenAgentNotFound(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_invalid',
                'coordinates' => [2.3522, 48.8566]
            ]
        ];

        $serviceOrder = $this->createMockServiceOrder();

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnCallback(function($encryptedId, $entityType) {
                if ($encryptedId === 'encrypted_order_123' && $entityType === EntityType::SERVICE_ORDER->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_invalid' && $entityType === EntityType::AGENT->value) {
                    return 999;
                }
                return null;
            });

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($serviceOrder);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent avec l\'ID encrypted_agent_invalid non trouvé');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testAssignAgentsToOrderThrowsExceptionWhenAgentNotAvailable(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1',
                'coordinates' => [2.3522, 48.8566]
            ]
        ];

        $serviceOrder = $this->createMockServiceOrder();
        $agent = $this->createMockAgent(1, 'Busy Agent');
        $activeTask = $this->createMock(Tasks::class);

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnCallback(function($encryptedId, $entityType) {
                if ($encryptedId === 'encrypted_order_123' && $entityType === EntityType::SERVICE_ORDER->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_1' && $entityType === EntityType::AGENT->value) {
                    return 1;
                }
                return null;
            });

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($serviceOrder);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($agent);

        // Agent has active tasks (not available)
        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'agent' => $agent,
                'status' => [Status::PENDING, Status::IN_PROGRESS]
            ])
            ->willReturn([$activeTask]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'agent Busy Agent n\'est pas disponible');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testAssignAgentsToOrderThrowsExceptionForInvalidCoordinates(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1',
                'coordinates' => [2.3522] // Missing latitude
            ]
        ];

        $serviceOrder = $this->createMockServiceOrder();
        $agent = $this->createMockAgent(1, 'Agent One');

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnCallback(function($encryptedId, $entityType) {
                if ($encryptedId === 'encrypted_order_123' && $entityType === EntityType::SERVICE_ORDER->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_1' && $entityType === EntityType::AGENT->value) {
                    return 1;
                }
                return null;
            });

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($serviceOrder);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assignation d\'agent #0: les coordonnées doivent être un tableau avec [longitude, latitude]');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testAssignAgentsToOrderThrowsExceptionForMissingAssignmentFields(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1'
                // Missing coordinates
            ]
        ];

        $serviceOrder = $this->createMockServiceOrder();

        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with('encrypted_order_123', EntityType::SERVICE_ORDER->value)
            ->willReturn(1);

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($serviceOrder);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assignation d\'agent #0: agentId et coordinates sont requis');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testAssignAgentsToOrderRollsBackOnException(): void
    {
        $encryptedOrderId = 'encrypted_order_123';
        $agentAssignments = [
            [
                'agentId' => 'encrypted_agent_1',
                'coordinates' => [2.3522, 48.8566]
            ]
        ];

        $serviceOrder = $this->createMockServiceOrder();
        $agent = $this->createMockAgent(1, 'Agent One');

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('decryptId')
            ->willReturnCallback(function($encryptedId, $entityType) {
                if ($encryptedId === 'encrypted_order_123' && $entityType === EntityType::SERVICE_ORDER->value) {
                    return 1;
                }
                if ($encryptedId === 'encrypted_agent_1' && $entityType === EntityType::AGENT->value) {
                    return 1;
                }
                return null;
            });

        $this->serviceOrdersRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($serviceOrder);

        $this->agentsRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($agent);

        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        // Simulate exception during flush
        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->taskService->assignAgentsToOrder($encryptedOrderId, $agentAssignments);
    }

    public function testGetTasksByOrder(): void
    {
        $serviceOrder = $this->createMockServiceOrder();
        $tasks = [$this->createMock(Tasks::class), $this->createMock(Tasks::class)];

        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['order' => $serviceOrder])
            ->willReturn($tasks);

        $result = $this->taskService->getTasksByOrder($serviceOrder);

        $this->assertEquals($tasks, $result);
    }

    public function testGetTasksByAgent(): void
    {
        $agent = $this->createMockAgent(1, 'Test Agent');
        $tasks = [$this->createMock(Tasks::class)];

        $this->tasksRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['agent' => $agent])
            ->willReturn($tasks);

        $result = $this->taskService->getTasksByAgent($agent);

        $this->assertEquals($tasks, $result);
    }

    private function createMockServiceOrder(): ServiceOrders
    {
        $serviceOrder = $this->createMock(ServiceOrders::class);
        $serviceOrder->method('getId')->willReturn(1);
        return $serviceOrder;
    }

    private function createMockAgent(int $id, string $name): Agents
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn($name);
        $user->method('getEmail')->willReturn(strtolower(str_replace(' ', '.', $name)) . '@example.com');
        $user->method('getRole')->willReturn(UserRole::AGENT);

        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn($id);
        $agent->method('getUser')->willReturn($user);
        $agent->method('getAddress')->willReturn('123 Test St');
        $agent->method('getSexe')->willReturn(Genre::M);
        $agent->method('getProfilePictureUrl')->willReturn('https://example.com/pic.jpg');

        return $agent;
    }
}
