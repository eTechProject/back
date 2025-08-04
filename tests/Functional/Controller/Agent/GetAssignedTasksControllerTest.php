<?php

namespace App\Tests\Functional\Controller\Agent;

use App\Service\AgentTaskService;
use App\DTO\Agent\Response\SimpleAssignedTaskDTO;
use App\DTO\Agent\Response\SimpleClientDTO;
use App\Enum\Status;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GetAssignedTasksControllerTest extends WebTestCase
{
    public function testGetAssignedTasksSuccess(): void
    {
        $client = static::createClient();
        
        // Mock the AgentTaskService
        $agentTaskService = $this->createMock(AgentTaskService::class);
        
        // Create mock DTOs
        $clientDTO = new SimpleClientDTO(
            'encrypted_client_id', 
            'John Doe',
            'john.doe@example.com'
        );
        
        $assignedTaskDTO = new SimpleAssignedTaskDTO(
            'encrypted_service_order_id',
            Status::PENDING->value,
            $clientDTO
        );
        
        $agentTaskService->method('getAssignedTasksByEncryptedAgentId')
            ->with('encrypted_agent_123')
            ->willReturn([$assignedTaskDTO]);
        
        // Replace the service in the container
        $client->getContainer()->set(AgentTaskService::class, $agentTaskService);

        // Make the request
        $client->request('GET', '/api/agent/encrypted_agent_123/assigned-tasks', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ]);

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertEquals(1, $responseData['total']);
        $this->assertCount(1, $responseData['data']);
    }

    public function testGetAssignedTasksAgentNotFound(): void
    {
        $client = static::createClient();
        
        // Mock the AgentTaskService to throw exception
        $agentTaskService = $this->createMock(AgentTaskService::class);
        $agentTaskService->method('getAssignedTasksByEncryptedAgentId')
            ->with('invalid_agent_id')
            ->willThrowException(new \InvalidArgumentException('Agent non trouvé'));
        
        // Replace the service in the container
        $client->getContainer()->set(AgentTaskService::class, $agentTaskService);

        // Make the request
        $client->request('GET', '/api/agent/invalid_agent_id/assigned-tasks', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ]);

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Agent non trouvé', $responseData['message']);
    }

    public function testGetAssignedTasksServerError(): void
    {
        $client = static::createClient();
        
        // Mock the AgentTaskService to throw generic exception
        $agentTaskService = $this->createMock(AgentTaskService::class);
        $agentTaskService->method('getAssignedTasksByEncryptedAgentId')
            ->with('encrypted_agent_123')
            ->willThrowException(new \Exception('Database error'));
        
        // Replace the service in the container
        $client->getContainer()->set(AgentTaskService::class, $agentTaskService);

        // Make the request
        $client->request('GET', '/api/agent/encrypted_agent_123/assigned-tasks', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ]);

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Erreur lors de la récupération des tâches assignées', $responseData['message']);
    }
}
