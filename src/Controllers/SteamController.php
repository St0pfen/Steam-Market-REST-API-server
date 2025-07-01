<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamMarketService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class SteamController
{
    private SteamMarketService $steamService;
    private ?LoggerInterface $logger;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->steamService = new SteamMarketService($logger);
        $this->logger = $logger;
    }
    
    /**
     * GET /api/v1/steam/item/{itemName}
     * Get the price of a specific item
     */
    public function getItemPrice(Request $request, Response $response, array $args): Response
    {
        // URL-Decoding for path parameters
        $itemName = urldecode($args['itemName'] ?? '');
        
        $queryParams = $request->getQueryParams();
        $appId = (int)($queryParams['app_id'] ?? 730);
        
        if (empty($itemName)) {
            $data = ['error' => 'Item name is required', 'success' => false];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // itemName is now correctly decoded
        $data = $this->steamService->getItemPrice($itemName, $appId);
        
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * GET /api/v1/steam/search
     * Search for items based on a query
     */
    public function searchItems(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $appId = (int)($queryParams['app_id'] ?? 730);
        $count = min((int)($queryParams['count'] ?? 10), 50); // Max 50 items
        
        if (empty($query)) {
            $data = [
                'error' => 'Search query parameter "q" is required',
                'example' => '/api/v1/steam/search?q=AK-47&app_id=730&count=10',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $data = $this->steamService->searchItems($query, $appId, $count);
        
        $statusCode = $data['success'] ? 200 : 500;
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * GET /api/v1/steam/popular
     * Get popular items for an app
     */
    public function getPopularItems(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $appId = (int)($queryParams['app_id'] ?? 730);
        
        $data = $this->steamService->getPopularItems($appId);
        
        $statusCode = $data['success'] ? 200 : 500;
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
    
    /**
     * GET /api/v1/steam/apps
     * Show supported Steam apps
     */
    public function getAppInfo(Request $request, Response $response): Response
    {
        $data = $this->steamService->getSupportedApps();
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * GET /api/v1/steam/status
     * API status and health check
     */
    public function getStatus(Request $request, Response $response): Response
    {
        $data = [
            'status' => 'online',
            'service' => 'Steam Market API Wrapper',
            'version' => '1.0.0',
            'endpoints' => [
                'GET /api/v1/steam/apps' => 'List of all supported Steam apps',
                'GET /api/v1/steam/item/{name}' => 'Price of a specific item',
                'GET /api/v1/steam/search?q={query}' => 'Search for items',
                'GET /api/v1/steam/popular' => 'Popular items',
                'GET /api/v1/steam/status' => 'API status'
            ],
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * GET /api/v1/steam/find-app
     * Search Steam apps by name
     */
    public function findAppByName(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        // Query parameters are automatically decoded
        $appName = $queryParams['name'] ?? '';
        
        if (empty($appName)) {
            $data = ['error' => 'App name is required', 'success' => false];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $data = $this->steamService->findAppByName($appName);
        
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * GET /api/v1/steam/app/{appId}
     * Get detailed information about a Steam app
     */
    public function getAppDetails(Request $request, Response $response, array $args): Response
    {
        $appId = (int)($args['appId'] ?? 0);
        
        if ($appId <= 0) {
            $data = [
                'error' => 'Valid app ID is required',
                'example' => '/api/v1/steam/app/730',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $data = $this->steamService->getAppDetails($appId);
        
        $statusCode = $data['success'] ? 200 : 404;
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
