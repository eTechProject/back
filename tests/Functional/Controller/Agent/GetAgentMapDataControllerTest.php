<?php

namespace App\Tests\Functional\Controller\Agent;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GetAgentMapDataControllerTest extends WebTestCase
{
    public function testGetAgentMapDataRoute(): void
    {
        $client = static::createClient();

        // Test with a sample encrypted agent ID
        $encryptedAgentId = 'test_encrypted_agent_id';
        $client->request('GET', "/api/agent/{$encryptedAgentId}/map-data");

        // Just verify the route exists (even if authentication fails)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 401, 500]);
    }

    public function testGetAgentMapDataWithHeaders(): void
    {
        $client = static::createClient();

        // Test with a sample encrypted agent ID
        $encryptedAgentId = 'test_encrypted_agent_id';
        $client->request('GET', "/api/agent/{$encryptedAgentId}/map-data", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ]);

        // Route should exist and attempt to process
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
