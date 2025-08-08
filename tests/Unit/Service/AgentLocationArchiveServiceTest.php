<?php

namespace App\Tests\Unit\Service;

use App\Entity\AgentLocationsArchive;
use App\Entity\AgentLocationsRaw;
use App\Entity\Agents;
use App\Entity\Tasks;
use App\Entity\User;
use App\Enum\Status;
use App\Enum\UserRole;
use App\Repository\AgentLocationsArchiveRepository;
use App\Repository\AgentLocationsRawRepository;
use App\Service\AgentLocationArchiveService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class AgentLocationArchiveServiceTest extends TestCase
{
    private AgentLocationArchiveService $archiveService;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|AgentLocationsRawRepository $rawRepository;
    private MockObject|AgentLocationsArchiveRepository $archiveRepository;
    private MockObject|LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->rawRepository = $this->createMock(AgentLocationsRawRepository::class);
        $this->archiveRepository = $this->createMock(AgentLocationsArchiveRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->archiveService = new AgentLocationArchiveService(
            $this->entityManager,
            $this->rawRepository,
            $this->archiveRepository,
            $this->logger
        );
    }

    public function testCreateTaskArchiveSuccess(): void
    {
        // Create mock entities
        $agent = $this->createMockAgent(1);
        $task = $this->createMockTask(1);
        $rawLocations = $this->createMockRawLocations();

        // Setup raw repository mock to return raw locations directly
        $this->rawRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use ($rawLocations) {
                $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
                $query = $this->createMock(\Doctrine\ORM\Query::class);
                
                $queryBuilder->method('where')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('setParameter')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('getQuery')->willReturn($query);
                
                $query->method('getResult')->willReturn($rawLocations);
                
                return $queryBuilder;
            });

        // Setup entity manager expectations
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AgentLocationsArchive::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Execute
        $result = $this->archiveService->createTaskArchive($agent, $task);

        // Assert
        $this->assertInstanceOf(AgentLocationsArchive::class, $result);
    }

    public function testCreateTaskArchiveNoRawLocations(): void
    {
        // Create mock entities
        $agent = $this->createMockAgent(1);
        $task = $this->createMockTask(1);

        // Setup raw repository mock to return empty array
        $this->rawRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(function () {
                $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
                $query = $this->createMock(\Doctrine\ORM\Query::class);
                
                $queryBuilder->method('where')->willReturnSelf();
                $queryBuilder->method('andWhere')->willReturnSelf();
                $queryBuilder->method('setParameter')->willReturnSelf();
                $queryBuilder->method('orderBy')->willReturnSelf();
                $queryBuilder->method('getQuery')->willReturn($query);
                
                $query->method('getResult')->willReturn([]);
                
                return $queryBuilder;
            });

        // Logger should log warning
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('No raw locations found for archiving');

        // Entity manager should not be called
        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Execute
        $result = $this->archiveService->createTaskArchive($agent, $task);

        // Assert
        $this->assertNull($result);
    }

    public function testArchiveExistsForTask(): void
    {
        $task = $this->createMockTask(1);
        $existingArchive = $this->createMock(AgentLocationsArchive::class);

        $this->archiveRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['task' => $task])
            ->willReturn($existingArchive);

        $result = $this->archiveService->archiveExistsForTask($task);

        $this->assertTrue($result);
    }

    public function testArchiveDoesNotExistForTask(): void
    {
        $task = $this->createMockTask(1);

        $this->archiveRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['task' => $task])
            ->willReturn(null);

        $result = $this->archiveService->archiveExistsForTask($task);

        $this->assertFalse($result);
    }

    private function createMockAgent(int $id): Agents
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn('agent@test.com');
        $user->method('getName')->willReturn('Test Agent');
        $user->method('getRole')->willReturn(UserRole::AGENT);

        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn($id);
        $agent->method('getUser')->willReturn($user);

        return $agent;
    }

    private function createMockTask(int $id): Tasks
    {
        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn($id);
        $task->method('getStatus')->willReturn(Status::IN_PROGRESS);
        $task->method('getDescription')->willReturn('Test Task');

        return $task;
    }

    private function createMockRawLocations(): array
    {
        $rawLocation1 = $this->createMock(AgentLocationsRaw::class);
        $rawLocation1->method('getGeom')->willReturn('POINT(2.352200 48.856600)');
        $rawLocation1->method('getRecordedAt')->willReturn(new \DateTimeImmutable('2025-08-08 10:00:00'));
        $rawLocation1->method('getSpeed')->willReturn(5.0);

        $rawLocation2 = $this->createMock(AgentLocationsRaw::class);
        $rawLocation2->method('getGeom')->willReturn('POINT(2.352300 48.856700)');
        $rawLocation2->method('getRecordedAt')->willReturn(new \DateTimeImmutable('2025-08-08 10:05:00'));
        $rawLocation2->method('getSpeed')->willReturn(3.0);

        $rawLocation3 = $this->createMock(AgentLocationsRaw::class);
        $rawLocation3->method('getGeom')->willReturn('POINT(2.352400 48.856800)');
        $rawLocation3->method('getRecordedAt')->willReturn(new \DateTimeImmutable('2025-08-08 10:10:00'));
        $rawLocation3->method('getSpeed')->willReturn(4.0);

        return [$rawLocation1, $rawLocation2, $rawLocation3];
    }
}
