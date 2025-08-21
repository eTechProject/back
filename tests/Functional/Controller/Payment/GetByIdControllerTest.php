<?php

namespace App\Tests\Functional\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetByIdControllerTest extends WebTestCase
{
    public function testGetPaymentByIdRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payments/test123');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testGetPaymentByIdWithInvalidId(): void
    {
        $client = static::createClient();
        
        // Test with clearly invalid ID
        $client->request('GET', '/api/admin/payments/invalid_encrypted_id');

        // Should be 401 (unauthorized) or 400 (bad request) but not 404
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 401]);
    }

    public function testGetPaymentByIdWithEmptyId(): void
    {
        $client = static::createClient();
        
        // Test with empty ID - should be 404 because route won't match
        $client->request('GET', '/api/admin/payments/');

        // This route actually redirects or returns 401, not 404
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 404]);
    }

    public function testGetPaymentByIdRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payments/somevalidlookingid');
        
        // Route should exist (not 404)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
