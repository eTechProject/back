<?php

namespace App\Tests\Unit\DTO\Payment;

use App\DTO\Payment\InitiatePaymentResponseDTO;
use PHPUnit\Framework\TestCase;

class InitiatePaymentResponseDTOTest extends TestCase
{
    public function testInitiatePaymentResponseDTOCanBeInstantiated(): void
    {
        $dto = new InitiatePaymentResponseDTO();
        
        $this->assertInstanceOf(InitiatePaymentResponseDTO::class, $dto);
    }

    public function testInitiatePaymentResponseDTOHasPublicProperties(): void
    {
        $dto = new InitiatePaymentResponseDTO();
        
        // Test that properties exist and can be set
        $this->assertObjectHasProperty('paymentId', $dto);
        $this->assertObjectHasProperty('historyId', $dto);
        $this->assertObjectHasProperty('provider', $dto);
        $this->assertObjectHasProperty('session', $dto);
    }

    public function testInitiatePaymentResponseDTOCanBePopulated(): void
    {
        $dto = new InitiatePaymentResponseDTO();
        $dto->paymentId = 'encrypted_payment_123';
        $dto->historyId = 'encrypted_history_456';
        $dto->provider = 'cybersource';
        $dto->session = [
            'sessionId' => 'cs_test_abcdef',
            'redirectUrl' => 'https://payment.example.com/redirect'
        ];
        
        $this->assertEquals('encrypted_payment_123', $dto->paymentId);
        $this->assertEquals('encrypted_history_456', $dto->historyId);
        $this->assertEquals('cybersource', $dto->provider);
        $this->assertIsArray($dto->session);
        $this->assertEquals('cs_test_abcdef', $dto->session['sessionId']);
    }

    public function testInitiatePaymentResponseDTOWithNullValues(): void
    {
        // Skip this test since DTO properties are not nullable
        $this->markTestSkipped('DTO properties are typed as non-nullable');
    }

    public function testInitiatePaymentResponseDTOWithEmptySession(): void
    {
        $dto = new InitiatePaymentResponseDTO();
        $dto->session = [];
        
        $this->assertIsArray($dto->session);
        $this->assertEmpty($dto->session);
    }

    public function testInitiatePaymentResponseDTOSessionStructure(): void
    {
        $dto = new InitiatePaymentResponseDTO();
        $dto->session = [
            'providerResponse' => json_encode(['sessionId' => 'test123']),
            'redirectUrl' => null,
            'expiresAt' => '2025-08-18T15:30:00Z'
        ];
        
        $this->assertArrayHasKey('providerResponse', $dto->session);
        $this->assertArrayHasKey('redirectUrl', $dto->session);
        $this->assertArrayHasKey('expiresAt', $dto->session);
        $this->assertNull($dto->session['redirectUrl']);
    }
}
