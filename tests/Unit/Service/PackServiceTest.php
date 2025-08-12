<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\PackService;
use App\Repository\PackRepository;
use App\Service\CryptService;
use Doctrine\ORM\EntityManagerInterface;
use App\DTO\Pack\Request\CreatePackDTO;
use App\DTO\Pack\Request\UpdatePackDTO;
use App\Entity\Pack;
use App\Enum\EntityType;

class PackServiceTest extends TestCase
{
    private PackRepository $packRepository;
    private EntityManagerInterface $entityManager;
    private CryptService $cryptService;
    private PackService $packService;

    protected function setUp(): void
    {
        $this->packRepository = $this->createMock(PackRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cryptService = $this->createMock(CryptService::class);
        
        $this->packService = new PackService(
            $this->packRepository,
            $this->entityManager,
            $this->cryptService
        );
    }

    public function testCreatePackFromRequestSuccess(): void
    {
        $createDTO = new CreatePackDTO();
        $createDTO->nbAgents = 5;
        $createDTO->prix = 99.99;
        $createDTO->description = 'Pack de test pour les tests unitaires';

        $pack = $this->packService->createPackFromRequest($createDTO);

        $this->assertInstanceOf(Pack::class, $pack);
        $this->assertSame(5, $pack->getNbAgents());
        $this->assertSame('99.99', $pack->getPrix()); // Prix est string dans l'entité
        $this->assertSame('Pack de test pour les tests unitaires', $pack->getDescription());
        $this->assertInstanceOf(\DateTimeInterface::class, $pack->getDateCreation());
    }

    public function testCreatePackWithTransactionSuccess(): void
    {
        $createDTO = new CreatePackDTO();
        $createDTO->nbAgents = 10;
        $createDTO->prix = 199.99;
        $createDTO->description = 'Pack premium pour les tests unitaires';

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');

        $pack = $this->packService->createPackWithTransaction($createDTO);

        $this->assertInstanceOf(Pack::class, $pack);
        $this->assertSame(10, $pack->getNbAgents());
        $this->assertSame('199.99', $pack->getPrix());
        $this->assertSame('Pack premium pour les tests unitaires', $pack->getDescription());
    }

    public function testCreatePackWithTransactionRollbackOnException(): void
    {
        $createDTO = new CreatePackDTO();
        $createDTO->nbAgents = 0; // Invalid value that should trigger business rule validation
        $createDTO->prix = 99.99;
        $createDTO->description = 'Pack invalide pour les tests unitaires';

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');
        // Commit ne devrait jamais être appelé car l'exception est lancée avant
        // mais persist et flush non plus car l'exception est lancée dans createPackFromRequest
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un pack doit avoir au moins 1 agent');

        $this->packService->createPackWithTransaction($createDTO);
    }

    public function testToDTO(): void
    {
        $pack = $this->createMock(Pack::class);
        $pack->method('getId')->willReturn(1);
        $pack->method('getNbAgents')->willReturn(5);
        $pack->method('getPrix')->willReturn('99.99'); // Prix est string dans l'entité
        $pack->method('getDescription')->willReturn('Pack de test');
        $pack->method('getDateCreation')->willReturn(new \DateTime('2025-08-12 10:00:00'));

        $this->cryptService
            ->expects($this->once())
            ->method('encryptId')
            ->with(1, EntityType::PACK->value)
            ->willReturn('encrypted_id_123');

        $packDTO = $this->packService->toDTO($pack);

        $this->assertSame('encrypted_id_123', $packDTO->id);
        $this->assertSame(5, $packDTO->nbAgents); // Propriété du DTO
        $this->assertSame('99.99', $packDTO->prix);
        $this->assertSame('Pack de test', $packDTO->description);
        $this->assertInstanceOf(\DateTimeInterface::class, $packDTO->dateCreation);
    }

    public function testFindById(): void
    {
        $pack = new Pack();
        
        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $result = $this->packService->findById(1);

        $this->assertSame($pack, $result);
    }

    public function testFindByIdNotFound(): void
    {
        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->packService->findById(999);

        $this->assertNull($result);
    }

    public function testFindAll(): void
    {
        $packs = [new Pack(), new Pack()];
        
        $this->packRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($packs);

        $result = $this->packService->findAll();

        $this->assertSame($packs, $result);
        $this->assertCount(2, $result);
    }

    public function testGetPacksPaginated(): void
    {
        $packs = [new Pack(), new Pack()];
        
        $this->packRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                [],
                ['dateCreation' => 'DESC'],
                10, // limit
                0   // offset
            )
            ->willReturn($packs);

        $this->packRepository
            ->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(25);

        $result = $this->packService->getPacksPaginated(1, 10);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($packs, $result[0]); // Premier élément: les packs
        $this->assertSame(25, $result[1]); // Deuxième élément: le total
    }

    public function testValidatePackBusinessRulesSuccess(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix(99.99);

        // Should not throw any exception
        $this->packService->validatePackBusinessRules($pack);
        $this->assertTrue(true); // Assertion to confirm test passed
    }

    public function testValidatePackBusinessRulesFailsWithZeroAgents(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(0);
        $pack->setPrix('99.99');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un pack doit avoir au moins 1 agent');

        $this->packService->validatePackBusinessRules($pack);
    }

    public function testValidatePackBusinessRulesFailsWithNegativeAgents(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(-5);
        $pack->setPrix('99.99');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un pack doit avoir au moins 1 agent');

        $this->packService->validatePackBusinessRules($pack);
    }

    public function testValidatePackBusinessRulesFailsWithZeroPrice(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix('0.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix du pack doit être positif');

        $this->packService->validatePackBusinessRules($pack);
    }

    public function testValidatePackBusinessRulesFailsWithNegativePrice(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix('-99.99');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix du pack doit être positif');

        $this->packService->validatePackBusinessRules($pack);
    }

    public function testUpdatePackSuccess(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix('99.99');
        $pack->setDescription('Description originale pour les tests');

        $updateDTO = new UpdatePackDTO(
            nb_agents: 10,
            prix: 149.99,
            description: 'Description mise à jour pour les tests'
        );

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');

        $result = $this->packService->updatePack(1, $updateDTO);

        $this->assertSame($pack, $result);
        $this->assertSame(10, $pack->getNbAgents());
        $this->assertSame('149.99', $pack->getPrix()); // Prix converti en string
        $this->assertSame('Description mise à jour pour les tests', $pack->getDescription());
    }

    public function testUpdatePackPartialUpdate(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix('99.99');
        $pack->setDescription('Description originale pour les tests');

        $updateDTO = new UpdatePackDTO(
            nb_agents: null,
            prix: 149.99,
            description: null
        );

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $result = $this->packService->updatePack(1, $updateDTO);

        $this->assertSame($pack, $result);
        $this->assertSame(5, $pack->getNbAgents()); // Unchanged
        $this->assertSame('149.99', $pack->getPrix()); // Updated and converted to string
        $this->assertSame('Description originale pour les tests', $pack->getDescription()); // Unchanged
    }

    public function testUpdatePackNotFound(): void
    {
        $updateDTO = new UpdatePackDTO(
            nb_agents: 10,
            prix: 149.99,
            description: 'Description mise à jour'
        );

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('beginTransaction');

        $result = $this->packService->updatePack(999, $updateDTO);

        $this->assertNull($result);
    }

    public function testUpdatePackRollbackOnException(): void
    {
        $pack = new Pack();
        $pack->setNbAgents(5);
        $pack->setPrix('99.99');

        $updateDTO = new UpdatePackDTO(
            nb_agents: 0, // Invalid value
            prix: null,
            description: null
        );

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un pack doit avoir au moins 1 agent');

        $this->packService->updatePack(1, $updateDTO);
    }

    public function testDeletePackSuccess(): void
    {
        $pack = new Pack();

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->with($pack);
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');

        $result = $this->packService->deletePack(1);

        $this->assertTrue($result);
    }

    public function testDeletePackNotFound(): void
    {
        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('beginTransaction');

        $result = $this->packService->deletePack(999);

        $this->assertFalse($result);
    }

    public function testDeletePackRollbackOnException(): void
    {
        $pack = new Pack();

        $this->packRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($pack);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->with($pack);
        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->packService->deletePack(1);
    }
}
