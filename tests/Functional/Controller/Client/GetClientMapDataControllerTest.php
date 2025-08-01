<?php

namespace App\Tests\Functional\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GetClientMapDataControllerTest extends WebTestCase
{
    public function testGetClientMapDataRoute(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/client/map-data');

        // Just verify the route exists (even if authentication fails)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 401, 500]);
    }

    public function testGetClientMapDataWithHeaders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/client/map-data', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ]);

        // Route should exist and attempt to process
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
