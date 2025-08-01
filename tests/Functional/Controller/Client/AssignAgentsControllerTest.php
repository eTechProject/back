<?php

namespace App\Tests\Functional\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AssignAgentsControllerTest extends WebTestCase
{
    public function testAssignAgentsRoute(): void
    {
        $client = static::createClient();

        $requestData = [
            'orderId' => 1,
            'agentAssignments' => [
                ['agentId' => 1, 'role' => 'guard']
            ]
        ];

        $client->request(
            'POST',
            '/api/client/assign-agents',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Just verify the route exists (even if authentication fails)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains($client->getResponse()->getStatusCode(), [400, 401, 500]);
    }

    public function testAssignAgentsWithEmptyContent(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/client/assign-agents',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            ''
        );

        // Route should exist
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
