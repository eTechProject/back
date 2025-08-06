<?php

namespace Tests\Unit\Service;

use App\Service\AgentTaskService;
use App\Service\TaskService;
use App\Service\CryptService;
use App\Repository\AgentsRepository;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\User;
use App\Entity\SecuredZones;
use App\Enum\EntityType;
use App\Enum\Status;
use App\Enum\UserRole;
use App\DTO\Agent\Response\SimpleAssignedTaskDTO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AgentTaskServiceTest extends TestCase
{
    private AgentTaskService $agentTaskService;
    private MockObject|TaskService $taskService;
    private MockObject|CryptService $cryptService;
    private MockObject|AgentsRepository $agentsRepository;

    protected function setUp(): void
    {
        $this->taskService = $this->createMock(TaskService::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->agentsRepository = $this->createMock(AgentsRepository::class);

        $this->agentTaskService = new AgentTaskService(
            $this->taskService,
            $this->cryptService,
            $this->agentsRepository
        );
    }

    public function testGetAssignedTasksByEncryptedAgentIdSuccess(): void
    {
        $encryptedUserId = 'encrypted_user_123';
        $userId = 1;
        
        // Create mock entities
        $agent = $this->createMockAgent();
        $task = $this->createMockTask();

        // Setup crypto service
        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with($encryptedUserId, EntityType::USER->value)
            ->willReturn($userId);

        // Setup agent repository
        $this->agentsRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $userId])
            ->willReturn($agent);

        // Setup task service
        $this->taskService
            ->expects($this->once())
            ->method('getTasksByAgent')
            ->with($agent)
            ->willReturn([$task]);

        // Setup encryption for DTOs
        $this->setupCryptServiceForDTOs();

        $result = $this->agentTaskService->getAssignedTasksByEncryptedAgentId($encryptedUserId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SimpleAssignedTaskDTO::class, $result[0]);
    }

    public function testGetAssignedTasksByEncryptedAgentIdAgentNotFound(): void
    {
        $encryptedUserId = 'encrypted_user_invalid';
        $userId = 999;

        $this->cryptService
            ->expects($this->once())
            ->method('decryptId')
            ->with($encryptedUserId, EntityType::USER->value)
            ->willReturn($userId);

        $this->agentsRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $userId])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent non trouvé');

        $this->agentTaskService->getAssignedTasksByEncryptedAgentId($encryptedUserId);
    }

    public function testGetAssignedTasksByUserSuccess(): void
    {
        $user = $this->createMockUser();
        $agent = $this->createMockAgent();
        $task = $this->createMockTask();

        $this->agentsRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user])
            ->willReturn($agent);

        $this->taskService
            ->expects($this->once())
            ->method('getTasksByAgent')
            ->with($agent)
            ->willReturn([$task]);

        $this->setupCryptServiceForDTOs();

        $result = $this->agentTaskService->getAssignedTasksByUser($user);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SimpleAssignedTaskDTO::class, $result[0]);
    }

    public function testGetAssignedTasksByUserAgentNotFound(): void
    {
        $user = $this->createMockUser();

        $this->agentsRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['user' => $user])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent non trouvé');

        $this->agentTaskService->getAssignedTasksByUser($user);
    }

    private function createMockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getName')->willReturn('Agent User');
        $user->method('getEmail')->willReturn('agent@example.com');
        $user->method('getRole')->willReturn(UserRole::AGENT);
        return $user;
    }

    private function createMockAgent(): Agents
    {
        $user = $this->createMockUser();
        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn(1);
        $agent->method('getUser')->willReturn($user);
        return $agent;
    }

    private function createMockTask(): Tasks
    {
        $client = $this->createMock(User::class);
        $client->method('getId')->willReturn(2);
        $client->method('getName')->willReturn('Client Name');

        $securedZone = $this->createMock(SecuredZones::class);
        $securedZone->method('getId')->willReturn(1);
        $securedZone->method('getName')->willReturn('Zone Alpha');

        $order = $this->createMock(ServiceOrders::class);
        $order->method('getId')->willReturn(1);
        $order->method('getDescription')->willReturn('Test order');
        $order->method('getStatus')->willReturn(Status::IN_PROGRESS);
        $order->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2025-08-01 10:00:00'));
        $order->method('getClient')->willReturn($client);
        $order->method('getSecuredZone')->willReturn($securedZone);

        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn(1);
        $task->method('getOrder')->willReturn($order);
        $task->method('getStatus')->willReturn(Status::PENDING);
        $task->method('getDescription')->willReturn('Test task');
        $task->method('getStartDate')->willReturn(new \DateTimeImmutable('2025-08-01 10:00:00'));
        $task->method('getEndDate')->willReturn(null);
        $task->method('getAssignPosition')->willReturn('POINT(2.352200 48.856600)');

        return $task;
    }

    private function setupCryptServiceForDTOs(): void
    {
        $this->cryptService
            ->method('encryptId')
            ->willReturnCallback(function($id, $entityType) {
                return "encrypted_{$entityType}_{$id}";
            });
    }
}
