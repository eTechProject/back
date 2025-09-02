<?php

namespace App\Tests\Unit\DTO\Payment;

use App\DTO\Payment\CreatePaymentDTO;
use PHPUnit\Framework\TestCase;

class CreatePaymentDTOTest extends TestCase
{
    public function testCreatePaymentDTOCanBeInstantiated(): void
    {
        $dto = new CreatePaymentDTO();
        
        $this->assertInstanceOf(CreatePaymentDTO::class, $dto);
        $this->assertNull($dto->packId);
        $this->assertNull($dto->amount);
        $this->assertNull($dto->currency);
    }

    public function testCreatePaymentDTOWithNumericPackId(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->packId = 123;
        $dto->amount = 99.99;
        $dto->currency = 'EUR';
        
        $this->assertEquals(123, $dto->packId);
        $this->assertEquals(99.99, $dto->amount);
        $this->assertEquals('EUR', $dto->currency);
    }

    public function testCreatePaymentDTOWithStringPackId(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->packId = 'encrypted_pack_id_123';
        $dto->amount = 49.99;
        $dto->currency = 'USD';
        
        $this->assertEquals('encrypted_pack_id_123', $dto->packId);
        $this->assertEquals(49.99, $dto->amount);
        $this->assertEquals('USD', $dto->currency);
    }

    public function testCreatePaymentDTOWithIntPackId(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->packId = 1;
        
        $this->assertIsInt($dto->packId);
        $this->assertEquals(1, $dto->packId);
    }

    public function testCreatePaymentDTOWithNullValues(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->packId = null;
        $dto->amount = null;
        $dto->currency = null;
        
        $this->assertNull($dto->packId);
        $this->assertNull($dto->amount);
        $this->assertNull($dto->currency);
    }

    public function testCreatePaymentDTOWithZeroAmount(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->amount = 0.0;
        
        $this->assertEquals(0.0, $dto->amount);
    }

    public function testCreatePaymentDTOWithNegativeAmount(): void
    {
        $dto = new CreatePaymentDTO();
        $dto->amount = -10.50;
        
        $this->assertEquals(-10.50, $dto->amount);
    }

    public function testCreatePaymentDTOCurrencyDefaults(): void
    {
        $dto = new CreatePaymentDTO();
        
        // Default currency should be null (to be set by service)
        $this->assertNull($dto->currency);
    }
}
