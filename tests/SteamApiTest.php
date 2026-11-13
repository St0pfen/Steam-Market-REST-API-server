<?php
/**
 * Steam API Integration Tests
 *
 * Prüft die realen HTTP-Endpunkte der API gegen einen laufenden Server.
 * Der Server wird von tests/bootstrap.php gestartet (php -S) oder über
 * TEST_BASE_URL als externer Dienst (z. B. Docker-Container) vorgegeben.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 2.0.0
 */

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SteamApiTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000';
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'http_errors' => false,
            'timeout' => 15,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $path): array
    {
        $response = $this->client->get($path);
        $raw = (string)$response->getBody();

        $data = json_decode($raw, true);
        $this->assertIsArray($data, "Antwort von {$path} ist kein JSON: {$raw}");

        return $data;
    }

    public function testApiStatus(): void
    {
        $response = $this->client->get('/api/v1/test');
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('is working!', $data['message']);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('Steam API', $data['endpoints']);
    }

    public function testDocsEndpoint(): void
    {
        $response = $this->client->get('/api/v1/docs');
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertArrayHasKey('steam', $data['endpoints']);
        $this->assertArrayHasKey(
            'GET /api/v1/steam/market/search/{itemName}',
            $data['endpoints']['steam']
        );
    }

    public function testSteamAppsEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/shop/apps');
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('apps', $data);
        $this->assertSame(730, $data['default_app']);
        $this->assertSame('Counter-Strike 2', $data['apps']['730']['name']);
        $this->assertTrue($data['apps']['730']['has_market']);
    }

    public function testSteamStatusEndpoint(): void
    {
        $response = $this->client->get('/api/v1/steam/shop/status');
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['success']);
        $this->assertSame('online', $data['status']);
        $this->assertArrayHasKey('service', $data);
    }

    public function testNotFoundEndpoint(): void
    {
        $response = $this->client->get('/api/v1/nonexistent');
        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['success']);
        $this->assertSame('Endpoint not found', $data['error']);
        $this->assertArrayHasKey('available_endpoints', $data);
    }

    public function testCorsPreflightHeaders(): void
    {
        $response = $this->client->request('OPTIONS', '/api/v1/test', [
            'headers' => [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'GET',
            ],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * Netzwerk-Test: spricht die echte Steam-API an. Nur mit NETWORK_TESTS=1.
     */
    #[Group('network')]
    public function testMarketSearchAgainstSteam(): void
    {
        if (getenv('NETWORK_TESTS') !== '1') {
            $this->markTestSkipped('NETWORK_TESTS=1 nicht gesetzt');
        }

        try {
            $response = $this->client->get('/api/v1/steam/market/search/AK-47', [
                'query' => ['app_id' => 730, 'count' => 1],
            ]);
        } catch (GuzzleException $e) {
            $this->fail('Request fehlgeschlagen: ' . $e->getMessage());
        }

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('success', $data);

        if ($data['success'] === true) {
            $this->assertContains($response->getStatusCode(), [200]);
            $this->assertArrayHasKey('items', $data);
        } else {
            // Steam kann rate-limiten oder offline sein — Fehlerform muss stimmen
            $this->assertArrayHasKey('error', $data);
        }
    }
}