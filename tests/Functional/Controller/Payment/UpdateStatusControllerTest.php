<?php

namespace App\Tests\Functional\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UpdateStatusControllerTest extends WebTestCase
{
    public function testUpdateStatusRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        // Test without authentication - should return 401
        $client->request('PUT', '/api/admin/payment/test123/status', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 'ACTIF']));

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testUpdateStatusWithInvalidId(): void
    {
        $client = static::createClient();
        
        // Test with invalid encrypted ID - should return 400 when authenticated
        // For now, just test that the route exists
        $client->request('PUT', '/api/admin/payment/invalid_id/status', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 'ACTIF']));

        // Should be 401 (unauthorized) or 400 (bad request) but not 404 (route not found)
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 401]);
    }

    public function testRoutesExist(): void
    {
        $client = static::createClient();
        
        // Test that our payment routes are registered
        $client->request('GET', '/api/admin/payments');
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/admin/payment-history');
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/payments');
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
