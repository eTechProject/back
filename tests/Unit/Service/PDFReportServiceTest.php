<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\PDFReportService;
use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\User;
use App\Entity\Agents;
use App\Enum\Status;
use App\Enum\TaskType;
use App\Enum\UserRole;
use App\Enum\Genre;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PDFReportServiceTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private PDFReportService $pdfReportService;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->pdfReportService = new PDFReportService($this->logger);
    }

    public function testGeneratePDFFilename(): void
    {
        $task = $this->createMockTask();
        
        $filename = $this->pdfReportService->generatePDFFilename($task);
        
        $this->assertStringContainsString('rapport_mission_', $filename);
        $this->assertStringContainsString('.pdf', $filename);
        $this->assertStringContainsString(date('Y-m-d'), $filename);
    }

    public function testGeneratePDFFromReportSuccess(): void
    {
        $task = $this->createMockTask();
        $reportContent = "## Test Report\n\nThis is a test report with **bold** text.";
        
        $pdfContent = $this->pdfReportService->generatePDFFromReport($reportContent, $task);
        
        $this->assertIsString($pdfContent);
        $this->assertNotEmpty($pdfContent);
        // Check if it's a PDF by looking for PDF header
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    private function createMockTask(): Tasks|MockObject
    {
        $client = $this->createMock(User::class);
        $client->method('getName')->willReturn('Test Client');
        $client->method('getEmail')->willReturn('client@test.com');

        $agentUser = $this->createMock(User::class);
        $agentUser->method('getName')->willReturn('Test Agent');

        $agent = $this->createMock(Agents::class);
        $agent->method('getUser')->willReturn($agentUser);
        $agent->method('getSexe')->willReturn(Genre::M);

        $serviceOrder = $this->createMock(ServiceOrders::class);
        $serviceOrder->method('getClient')->willReturn($client);

        $task = $this->createMock(Tasks::class);
        $task->method('getId')->willReturn(1);
        $task->method('getDescription')->willReturn('Test mission');
        $task->method('getType')->willReturn(TaskType::SURVEILLANCE);
        $task->method('getStatus')->willReturn(Status::COMPLETED);
        $task->method('getOrder')->willReturn($serviceOrder);
        $task->method('getAgent')->willReturn($agent);

        return $task;
    }
}
