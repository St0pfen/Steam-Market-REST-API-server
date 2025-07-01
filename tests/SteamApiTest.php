<?php
/**
 * Steam API Integration Tests
 * 
 * PHPUnit test suite for testing Steam Market REST API endpoints
 * including basic functionality, error handling, and response validation.
 *
 * @package stopfen/steam-rest-api-php
 * @author @StopfMich
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Steam API Test Class
 * 
 * Contains integration tests for all Steam Market API endpoints
 * to ensure proper functionality and response formatting.
 */
class SteamApiTest extends TestCase
{
    /**
     * HTTP client for making API requests
     * @var Client
     */
    private Client $client;
    
    /**
     * Base URL for API testing
     * @var string
     */
    private string $baseUrl = 'http://localhost:8000';
    
    /**
     * Set up test environment
     * 
     * Initializes the HTTP client with base configuration
     * for testing API endpoints.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }
    
    /**
     * Test API status endpoint
     * 
     * Verifies that the basic API test endpoint returns correct
     * status code and expected response structure.
     *
     * @return void
     */
    public function testApiStatus(): void
    {
        $response = $this->client->get('/api/v1/test');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
    }
    
    /**
     * Test Steam apps endpoint
     * 
     * Verifies that the Steam apps endpoint returns the list of
     * supported applications with proper structure.
     *
     * @return void
     */
    public function testSteamAppsEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/apps');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('apps', $data);
        $this->assertArrayHasKey(730, $data['apps']); // CS:GO
    }
    
    /**
     * Test Steam status endpoint
     * 
     * Verifies that the Steam API status endpoint returns proper
     * status information and health check data.
     *
     * @return void
     */
    public function testSteamStatusEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/status');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('online', $data['status']);
    }
    
    /**
     * Test search items with valid query
     * 
     * Tests the search functionality with a valid query parameter
     * and handles both success and error cases gracefully.
     *
     * @return void
     */
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
    
    /**
     * Test search items without required query parameter
     * 
     * Verifies that the search endpoint properly validates required
     * parameters and returns appropriate error responses.
     *
     * @return void
     */
    public function testSearchItemsWithoutQuery(): void
    {
        $response = $this->client->get('/api/v1/steam/search');
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }
    
    /**
     * Test 404 error handling for non-existent endpoints
     * 
     * Verifies that the API properly handles requests to non-existent
     * endpoints with appropriate 404 responses.
     *
     * @return void
     */
    public function testNotFoundEndpoint(): void
    {
        $response = $this->client->get('/api/v1/nonexistent');
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
    }
}
