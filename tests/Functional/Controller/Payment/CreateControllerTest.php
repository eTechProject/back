<?php

namespace App\Tests\Functional\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateControllerTest extends WebTestCase
{
    public function testCreatePaymentRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/payments', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'packId' => 1,
            'amount' => 99.99,
            'currency' => 'EUR'
        ]));

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testCreatePaymentWithInvalidData(): void
    {
        $client = static::createClient();
        
        // Test with missing required data
        $client->request('POST', '/api/payments', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        // Should be 401 (unauthorized) or 400 (bad request) but not 404
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 401]);
    }

    public function testCreatePaymentWithInvalidJson(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/payments', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        // Should be 401 (unauthorized) or 400 (bad request) but not 404
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 401]);
    }

    public function testCreatePaymentRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/payments');
        
        // Route should exist (not 404)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
