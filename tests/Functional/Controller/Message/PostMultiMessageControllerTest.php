<?php

namespace App\Tests\Functional\Controller\Message;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PostMultiMessageControllerTest extends WebTestCase
{
    public function testMultiMessageEndpointExists(): void
    {
        $client = static::createClient();

        // Test that the route exists and requires authentication
        $client->request('POST', '/api/messages/multi', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], '{}');

        // Should not return 404 (route exists)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
        
        // Should return 401 (unauthorized) as no authentication provided
        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testMultiMessageWithInvalidJson(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ], 'invalid-json');

        // Route should exist and attempt to process
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testMultiMessageWithEmptyBody(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'CONTENT_TYPE' => 'application/json'
        ], '');

        // Route should exist and attempt to process
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }
}
