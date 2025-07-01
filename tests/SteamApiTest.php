<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SteamApiTest extends TestCase
{
    private Client $client;
    private string $baseUrl = 'http://localhost:8000';
    
    protected function setUp(): void
    {
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }
    
    public function testApiStatus(): void
    {
        $response = $this->client->get('/api/v1/test');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
    }
    
    public function testSteamAppsEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/apps');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('apps', $data);
        $this->assertArrayHasKey(730, $data['apps']); // CS:GO
    }
    
    public function testSteamStatusEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/status');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('online', $data['status']);
    }
    
    public function testSearchItemsWithValidQuery(): void
    {
        $response = $this->client->get('/api/v1/steam/search?q=AK-47&count=1');
        $data = json_decode($response->getBody()->getContents(), true);
        
        $this->assertArrayHasKey('success', $data);
        if ($data['success']) {
            $this->assertArrayHasKey('items', $data);
        } else {
            // Steam API could be offline or rate-limiting
            $this->assertArrayHasKey('error', $data);
        }
    }
    
    public function testSearchItemsWithoutQuery(): void
    {
        $response = $this->client->get('/api/v1/steam/search');
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }
    
    public function testNotFoundEndpoint(): void
    {
        $response = $this->client->get('/api/v1/nonexistent');
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }
}
