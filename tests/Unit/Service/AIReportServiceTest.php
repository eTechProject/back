<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\AIReportService;
use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\User;
use App\Entity\Agents;
use App\Entity\AgentLocationsArchive;
use App\Enum\Status;
use App\Enum\TaskType;
use App\Enum\UserRole;
use App\Repository\AgentLocationsArchiveRepository;
use App\Repository\MessagesRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AIReportServiceTest extends TestCase
{
    private HttpClientInterface|MockObject $httpClient;
    private AgentLocationsArchiveRepository|MockObject $archiveRepository;
    private MessagesRepository|MockObject $messagesRepository;
    private LoggerInterface|MockObject $logger;
    private AIReportService $aiReportService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->archiveRepository = $this->createMock(AgentLocationsArchiveRepository::class);
        $this->messagesRepository = $this->createMock(MessagesRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->aiReportService = new AIReportService(
            $this->httpClient,
            $this->archiveRepository,
            $this->messagesRepository,
            $this->logger,
            'test_api_key'
        );
    }

    public function testGenerateTaskReportSuccess(): void
    {
        // Create mock entities
        $task = $this->createMockTask();
        $archive = $this->createMockArchive();
        
        // Mock repository calls
        $this->archiveRepository
            ->expects($this->once())
            ->method('findByTaskId')
            ->with($task->getId())
            ->willReturn($archive);

        $this->messagesRepository
            ->expects($this->once())
            ->method('findMessagesByOrderAndDateRange')
            ->with(
                $this->anything(), // order ID
                $this->isInstanceOf(\DateTimeInterface::class), // start date
                $this->isInstanceOf(\DateTimeInterface::class) // end date
            )
            ->willReturn([]);

        // Mock HTTP client response
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'Rapport de mission généré par IA...'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        // Execute the service
        $result = $this->aiReportService->generateTaskReport($task);

        // Assert the result
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Rapport généré avec succès', $result['message']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('report', $result['data']);
        $this->assertEquals('Rapport de mission généré par IA...', $result['data']['report']);
    }

    public function testGenerateTaskReportHttpClientError(): void
    {
        $task = $this->createMockTask();
        
        // Mock repository calls
        $this->archiveRepository->method('findByTaskId')->willReturn(null);
        $this->messagesRepository->method('findMessagesByOrderAndDateRange')->willReturn([]);

        // Mock HTTP client to throw exception
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('HTTP Error'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        // Expect exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erreur lors de la génération du rapport');

        $this->aiReportService->generateTaskReport($task);
    }

    private function createMockTask(): Tasks|MockObject
    {
        $client = $this->createMock(User::class);
        $client->method('getName')->willReturn('Client Test');
        $client->method('getEmail')->willReturn('client@test.com');
        $client->method('getPhone')->willReturn('0123456789');

        $agentUser = $this->createMock(User::class);
        $agentUser->method('getName')->willReturn('Agent Test');
        $agentUser->method('getPhone')->willReturn('0987654321');

        $agent = $this->createMock(Agents::class);
        $agent->method('getUser')->willReturn($agentUser);
        $agent->method('getSexe')->willReturn('M');
        $agent->method('getAddress')->willReturn('123 Rue Test');
        $agent->method('getProfilePictureUrl')->willReturn('http://example.com/avatar.jpg');

        $serviceOrder = $this->createMock(ServiceOrders::class);
        $serviceOrder->method('getClient')->willReturn($client);

        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn(1);
        $task->method('getDescription')->willReturn('Mission de sécurité test');
        $task->method('getType')->willReturn(TaskType::SURVEILLANCE);
        $task->method('getAssignPosition')->willReturn('POINT(2.3522 48.8566)');
        $task->method('getStartDate')->willReturn(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $task->method('getEndDate')->willReturn(new \DateTimeImmutable('2025-01-01 18:00:00'));
        $task->method('getStatus')->willReturn(Status::COMPLETED);
        $task->method('getOrder')->willReturn($serviceOrder);
        $task->method('getAgent')->willReturn($agent);

        return $task;
    }

    private function createMockArchive(): AgentLocationsArchive|MockObject
    {
        $archive = $this->createMock(AgentLocationsArchive::class);
        $archive->method('getPathLength')->willReturn(5000.0);
        $archive->method('getAvgSpeed')->willReturn(1.5); // m/s
        $archive->method('getStartTime')->willReturn(new \DateTimeImmutable('2025-01-01 10:00:00'));
        $archive->method('getEndTime')->willReturn(new \DateTimeImmutable('2025-01-01 18:00:00'));
        $archive->method('getPointCount')->willReturn(150);

        return $archive;
    }
}
