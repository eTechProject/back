<?php

namespace Tests\Unit\Service;

use App\Service\AgentService;
use App\Repository\AgentsRepository;
use App\Service\CryptService;
use App\Entity\Agents;
use App\Entity\User;
use App\Enum\Genre;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\Agent\Response\AgentResponseDTO;
use App\DTO\User\Internal\UserDTO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AgentServiceAvailableAgentsTest extends TestCase
{
    private MockObject|AgentService $agentService;
    private MockObject|AgentsRepository $agentsRepository;
    private MockObject|CryptService $cryptService;

    protected function setUp(): void
    {
        $this->agentsRepository = $this->createMock(AgentsRepository::class);
        $this->cryptService = $this->createMock(CryptService::class);
        
        // Create a real AgentService instance for testing
        $this->agentService = new AgentService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\App\Repository\UserRepository::class),
            $this->agentsRepository,
            $this->cryptService,
            $this->createMock(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)
        );
    }

    public function testGetAvailableAgentsReturnsEmptyArrayWhenNoAgents(): void
    {
        $this->agentsRepository
            ->expects($this->once())
            ->method('findAvailableAgents')
            ->willReturn([]);

        $result = $this->agentService->getAvailableAgents();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAvailableAgentsReturnsCorrectDTOs(): void
    {
        // Create mock user
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(10);
        $user->method('getName')->willReturn('John Doe');
        $user->method('getEmail')->willReturn('john@example.com');
        $user->method('getRole')->willReturn(UserRole::AGENT);

        // Create mock agent
        $agent = $this->createMock(Agents::class);
        $agent->method('getId')->willReturn(1);
        $agent->method('getUser')->willReturn($user);
        $agent->method('getAddress')->willReturn('123 Main St');
        $agent->method('getSexe')->willReturn(Genre::M);
        $agent->method('getProfilePictureUrl')->willReturn('https://example.com/pic.jpg');

        $this->agentsRepository
            ->expects($this->once())
            ->method('findAvailableAgents')
            ->willReturn([$agent]);

        $this->cryptService
            ->expects($this->exactly(2))
            ->method('encryptId')
            ->willReturnCallback(function($id, $entityType) {
                if ($id === 1 && $entityType === EntityType::AGENT->value) {
                    return 'encrypted_agent_id';
                }
                if ($id === 10 && $entityType === EntityType::USER->value) {
                    return 'encrypted_user_id';
                }
                return null;
            });

        $result = $this->agentService->getAvailableAgents();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        
        $dto = $result[0];
        $this->assertInstanceOf(AgentResponseDTO::class, $dto);
        $this->assertEquals('encrypted_agent_id', $dto->agentId);
        $this->assertEquals('123 Main St', $dto->address);
        $this->assertEquals(Genre::M, $dto->sexe);
        $this->assertEquals('https://example.com/pic.jpg', $dto->profilePictureUrl);
        $this->assertInstanceOf(UserDTO::class, $dto->user);
    }

    public function testGetAvailableAgentsWithMultipleAgents(): void
    {
        // Create multiple mock agents
        $agents = [];
        $encryptionMap = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $user = $this->createMock(User::class);
            $user->method('getId')->willReturn($i + 10);
            $user->method('getName')->willReturn("Agent $i");
            $user->method('getEmail')->willReturn("agent$i@example.com");
            $user->method('getRole')->willReturn(UserRole::AGENT);

            $agent = $this->createMock(Agents::class);
            $agent->method('getId')->willReturn($i);
            $agent->method('getUser')->willReturn($user);
            $agent->method('getAddress')->willReturn("Address $i");
            $agent->method('getSexe')->willReturn($i % 2 === 0 ? Genre::F : Genre::M);
            $agent->method('getProfilePictureUrl')->willReturn(null);

            $agents[] = $agent;
            $encryptionMap[] = [$i, EntityType::AGENT->value, "encrypted_agent_id_$i"];
            $encryptionMap[] = [$i + 10, EntityType::USER->value, "encrypted_user_id_$i"];
        }

        $this->agentsRepository
            ->expects($this->once())
            ->method('findAvailableAgents')
            ->willReturn($agents);

        $this->cryptService
            ->expects($this->exactly(6))
            ->method('encryptId')
            ->willReturnCallback(function($id, $entityType) {
                if ($entityType === EntityType::AGENT->value) {
                    return "encrypted_agent_id_$id";
                }
                if ($entityType === EntityType::USER->value) {
                    $agentId = $id - 10;
                    return "encrypted_user_id_$agentId";
                }
                return null;
            });

        $result = $this->agentService->getAvailableAgents();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        
        foreach ($result as $index => $dto) {
            $expectedIndex = $index + 1;
            $this->assertInstanceOf(AgentResponseDTO::class, $dto);
            $this->assertEquals("encrypted_agent_id_$expectedIndex", $dto->agentId);
        }
    }
}
