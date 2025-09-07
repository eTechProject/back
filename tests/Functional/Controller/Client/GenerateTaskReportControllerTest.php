<?php

namespace App\Tests\Functional\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GenerateTaskReportControllerTest extends WebTestCase
{
    public function testGenerateTaskReportRouteExists(): void
    {
        $client = static::createClient();

        // Test with a sample encrypted task ID
        $encryptedTaskId = 'test_encrypted_task_id';
        $client->request('POST', "/api/client/task/{$encryptedTaskId}/generate-report");

        // Just verify the route exists (even if authentication fails)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403, 500]);
    }

    public function testGenerateTaskReportWithAuth(): void
    {
        $client = static::createClient();

        // Test with a sample encrypted task ID and auth header
        $encryptedTaskId = 'test_encrypted_task_id';
        $client->request(
            'POST',
            "/api/client/task/{$encryptedTaskId}/generate-report",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
                'CONTENT_TYPE' => 'application/json'
            ]
        );

        // Route should exist and attempt to process
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testGenerateTaskReportMethodNotAllowed(): void
    {
        $client = static::createClient();

        // Test with GET method (should not be allowed)
        $encryptedTaskId = 'test_encrypted_task_id';
        $client->request('GET', "/api/client/task/{$encryptedTaskId}/generate-report");

        // Should return 405 Method Not Allowed
        $this->assertEquals(405, $client->getResponse()->getStatusCode());
    }
}
