<?php

namespace App\Tests\Unit\Service;

use App\Service\PaymentService;
use App\Service\CybersourceClient;
use App\Service\CryptService;
use App\Repository\PaymentRepository;
use App\Repository\PaymentHistoryRepository;
use App\DTO\Payment\CreatePaymentDTO;
use App\Entity\Payment;
use App\Entity\PaymentHistory;
use App\Entity\Pack;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\PaymentHistoryStatus;
use App\Enum\EntityType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;
    private MockObject $entityManager;
    private MockObject $paymentRepository;
    private MockObject $paymentHistoryRepository;
    private MockObject $cybersourceClient;
    private MockObject $cryptService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->paymentHistoryRepository = $this->createMock(PaymentHistoryRepository::class);
        $this->cybersourceClient = $this->createMock(CybersourceClient::class);
        $this->cryptService = $this->createMock(CryptService::class);

        $this->paymentService = new PaymentService(
            $this->entityManager,
            $this->paymentRepository,
            $this->paymentHistoryRepository,
            $this->cybersourceClient,
            $this->cryptService
        );
    }

    public function testInitiatePaymentSuccess(): void
    {
        // Mock data
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('test@example.com');

        $pack = $this->createMock(Pack::class);
        $pack->method('getId')->willReturn(1);

        $dto = new CreatePaymentDTO();
        $dto->packId = 1;
        $dto->amount = 99.99;
        $dto->currency = 'EUR';

        // Mock repository to return pack
        $packRepository = $this->createMock(\App\Repository\PackRepository::class);
        $packRepository->method('find')->with(1)->willReturn($pack);
        
        $this->entityManager->method('getRepository')
            ->with(\App\Entity\Pack::class)
            ->willReturn($packRepository);

        // Mock Cybersource response
        $cybersourceResponse = [
            'providerResponse' => json_encode(['sessionId' => 'cs_test_123456']),
            'redirectUrl' => null
        ];
        $this->cybersourceClient->method('createPaymentSession')->willReturn($cybersourceResponse);

        // Mock CryptService
        $this->cryptService->method('encryptId')
            ->willReturnCallback(function($id, $type) {
                return "encrypted_{$type}_{$id}";
            });

        // Mock entity manager persist and flush
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        // Execute
        $result = $this->paymentService->initiatePayment($user, $dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('paymentId', $result);
        $this->assertArrayHasKey('historyId', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('session', $result);
        $this->assertEquals('cybersource', $result['provider']);
    }

    public function testInitiatePaymentWithEncryptedPackId(): void
    {
        // Mock data
        $user = $this->createMock(User::class);
        $pack = $this->createMock(Pack::class);
        $pack->method('getId')->willReturn(1);

        $dto = new CreatePaymentDTO();
        $dto->packId = 'encrypted_pack_1';
        $dto->amount = 49.99;

        // Mock CryptService to decrypt pack ID
        $this->cryptService->method('decryptId')
            ->with('encrypted_pack_1', EntityType::PACK->value)
            ->willReturn(1); // Return int, not string

        // Mock repository to return pack
        $packRepository = $this->createMock(\App\Repository\PackRepository::class);
        $packRepository->method('find')->with(1)->willReturn($pack);
        
        $this->entityManager->method('getRepository')
            ->with(\App\Entity\Pack::class)
            ->willReturn($packRepository);

        // Mock other dependencies
        $this->cybersourceClient->method('createPaymentSession')->willReturn([
            'providerResponse' => json_encode(['sessionId' => 'test']),
            'redirectUrl' => null
        ]);
        
        $this->cryptService->method('encryptId')->willReturn('encrypted_id');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Execute - should not throw exception
        $result = $this->paymentService->initiatePayment($user, $dto);
        
        $this->assertIsArray($result);
    }

    public function testInitiatePaymentThrowsExceptionForInvalidPack(): void
    {
        $user = $this->createMock(User::class);
        $dto = new CreatePaymentDTO();
        $dto->packId = 999; // Non-existent pack

        // Mock repository to return null (pack not found)
        $packRepository = $this->createMock(\App\Repository\PackRepository::class);
        $packRepository->method('find')->with(999)->willReturn(null);
        
        $this->entityManager->method('getRepository')
            ->with(\App\Entity\Pack::class)
            ->willReturn($packRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pack introuvable');

        $this->paymentService->initiatePayment($user, $dto);
    }

    public function testGetPaymentsPaginated(): void
    {
        $payments = [$this->createMock(Payment::class)];
        $total = 5;

        // Mock repository methods
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class); // Use concrete Query class
        
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn($payments);

        // Mock count query
        $countQueryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $countQuery = $this->createMock(\Doctrine\ORM\Query::class); // Use concrete Query class
        
        $countQueryBuilder->method('select')->willReturnSelf();
        $countQueryBuilder->method('getQuery')->willReturn($countQuery);
        $countQuery->method('getSingleScalarResult')->willReturn($total);

        $this->paymentRepository->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($queryBuilder, $countQueryBuilder);

        // Execute
        [$resultPayments, $resultTotal] = $this->paymentService->getPaymentsPaginated(1, 20);

        // Assert
        $this->assertEquals($payments, $resultPayments);
        $this->assertEquals($total, $resultTotal);
    }
}
