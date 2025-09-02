<?php

namespace App\Tests\Unit\Service;

use App\Service\CybersourceClient;
use PHPUnit\Framework\TestCase;

class CybersourceClientTest extends TestCase
{
    private CybersourceClient $cybersourceClient;

    protected function setUp(): void
    {
        $this->cybersourceClient = new CybersourceClient(
            'test_api_key',
            'test_org_id',
            'test_shared_secret'
        );
    }

    public function testCreatePaymentSessionReturnsArray(): void
    {
        $payload = [
            'amount' => 99.99,
            'currency' => 'EUR',
            'reference' => 'payment_123',
            'customer' => [
                'id' => 1,
                'email' => 'test@example.com'
            ]
        ];

        $result = $this->cybersourceClient->createPaymentSession($payload);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('providerResponse', $result);
        $this->assertArrayHasKey('redirectUrl', $result);
    }

    public function testCreatePaymentSessionProviderResponseIsJson(): void
    {
        $payload = [
            'amount' => 49.99,
            'currency' => 'USD'
        ];

        $result = $this->cybersourceClient->createPaymentSession($payload);
        
        $providerResponse = $result['providerResponse'];
        $this->assertIsString($providerResponse);
        
        // Should be valid JSON
        $decoded = json_decode($providerResponse, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('sessionId', $decoded);
    }

    public function testCreatePaymentSessionWithEmptyPayload(): void
    {
        $result = $this->cybersourceClient->createPaymentSession([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('providerResponse', $result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertNull($result['redirectUrl']);
    }

    public function testCreatePaymentSessionSessionIdFormat(): void
    {
        $result = $this->cybersourceClient->createPaymentSession(['test' => 'data']);
        
        $providerResponse = json_decode($result['providerResponse'], true);
        $sessionId = $providerResponse['sessionId'];
        
        // Should start with 'cs_test_'
        $this->assertStringStartsWith('cs_test_', $sessionId);
        
        // Should be followed by 12 hex characters (6 bytes * 2)
        $this->assertMatchesRegularExpression('/^cs_test_[a-f0-9]{12}$/', $sessionId);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $client = new CybersourceClient();
        
        // Should still work with default empty parameters
        $result = $client->createPaymentSession(['test' => 'data']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('providerResponse', $result);
    }

    public function testConstructorWithCustomValues(): void
    {
        $client = new CybersourceClient(
            'custom_api_key',
            'custom_org_id',
            'custom_secret'
        );
        
        $result = $client->createPaymentSession(['test' => 'data']);
        
        $this->assertIsArray($result);
        // In real implementation, these parameters would be used
        // For now, just verify the method works
        $this->assertArrayHasKey('providerResponse', $result);
    }
}
