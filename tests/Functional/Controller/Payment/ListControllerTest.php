<?php

namespace App\Tests\Functional\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ListControllerTest extends WebTestCase
{
    public function testListPaymentsRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payments');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testListPaymentsWithPagination(): void
    {
        $client = static::createClient();
        
        // Test with pagination parameters
        $client->request('GET', '/api/admin/payments?page=1&limit=10');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testListPaymentsWithInvalidPagination(): void
    {
        $client = static::createClient();
        
        // Test with invalid pagination
        $client->request('GET', '/api/admin/payments?page=0&limit=0');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testListPaymentsRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payments');
        
        // Route should exist (not 404)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
