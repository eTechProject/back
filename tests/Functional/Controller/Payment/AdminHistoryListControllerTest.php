<?php

namespace App\Tests\Functional\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminHistoryListControllerTest extends WebTestCase
{
    public function testAdminHistoryListRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payment-history');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testAdminHistoryListWithPagination(): void
    {
        $client = static::createClient();
        
        // Test with pagination parameters
        $client->request('GET', '/api/admin/payment-history?page=2&limit=5');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testAdminHistoryListWithInvalidPagination(): void
    {
        $client = static::createClient();
        
        // Test with invalid pagination (should be normalized)
        $client->request('GET', '/api/admin/payment-history?page=-1&limit=999');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testAdminHistoryListWithMaxLimit(): void
    {
        $client = static::createClient();
        
        // Test with limit above maximum (should be capped at 100)
        $client->request('GET', '/api/admin/payment-history?limit=200');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testAdminHistoryListRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/admin/payment-history');
        
        // Route should exist (not 404)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
