<?php

namespace App\Tests\Functional\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTestControllerTest extends WebTestCase
{
    public function testHealthEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/public/health');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('API is healthy and running', $responseData['message']);
        $this->assertArrayHasKey('timestamp', $responseData);
        $this->assertArrayHasKey('version', $responseData);
    }

    public function testInfoEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/public/info');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('eTech Agent Location API', $responseData['data']['api_name']);
        $this->assertArrayHasKey('available_endpoints', $responseData['data']);
    }

    public function testTestEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/public/test');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Test endpoint is working correctly', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('server_time', $responseData['data']);
        $this->assertArrayHasKey('random_number', $responseData['data']);
        $this->assertArrayHasKey('test_data', $responseData['data']);
    }

    public function testEchoEndpoint(): void
    {
        $client = static::createClient();

        $testData = [
            'message' => 'Hello World',
            'data' => [
                'key' => 'value',
                'number' => 123
            ]
        ];

        $client->request(
            'POST',
            '/api/public/echo',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Echo endpoint - your data returned', $responseData['message']);
        $this->assertEquals($testData, $responseData['received_data']);
        $this->assertEquals('POST', $responseData['method']);
        $this->assertArrayHasKey('timestamp', $responseData);
    }

    public function testEchoEndpointWithEmptyBody(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/public/echo');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertNull($responseData['received_data']);
        $this->assertEquals('', $responseData['raw_content']);
    }

    public function testAllEndpointsReturnJsonContentType(): void
    {
        $client = static::createClient();

        $endpoints = [
            '/api/public/health',
            '/api/public/info',
            '/api/public/test'
        ];

        foreach ($endpoints as $endpoint) {
            $client->request('GET', $endpoint);
            $response = $client->getResponse();
            
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        }
    }
}
