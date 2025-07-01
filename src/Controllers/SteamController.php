<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamMarketService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Steam Controller
 * 
 * Handles all Steam Market API related endpoints including item pricing,
 * search functionality, app information, and market data retrieval.
 * Integrates with Steam Market API through the SteamMarketService.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class SteamController
{
    /**
     * Steam Market service instance for API calls
     * @var SteamMarketService
     */
    private SteamMarketService $steamService;
    
    /**
     * Optional logger instance for request logging
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;
    
    /**
     * SteamController constructor
     * 
     * Initializes the controller with optional logger and creates
     * a new instance of SteamMarketService for handling API calls.
     *
     * @param LoggerInterface|null $logger Optional logger instance for request logging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->steamService = new SteamMarketService($logger);
        $this->logger = $logger;
    }
    
    /**
     * Get Steam Market item price
     * 
     * Retrieves pricing information for a specific Steam Market item
     * including lowest price, median price, and trading volume.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @param array $args Route arguments containing itemName
     * @return Response JSON response with item price data or error message
     * 
     * @route GET /api/v1/steam/item/{itemName}
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
     * Search Steam Market items
     * 
     * Searches for Steam Market items based on a query string.
     * Supports filtering by app ID and limiting result count.
     *
     * @param Request $request The HTTP request object containing query parameters
     * @param Response $response The HTTP response object
     * @return Response JSON response with search results or error message
     * 
     * @route GET /api/v1/steam/search
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
     * Get popular Steam Market items
     * 
     * Retrieves a list of popular items for a specific Steam application.
     * Useful for discovering trending items and market activity.
     *
     * @param Request $request The HTTP request object containing query parameters
     * @param Response $response The HTTP response object
     * @return Response JSON response with popular items or error message
     * 
     * @route GET /api/v1/steam/popular
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
     * Get supported Steam applications
     * 
     * Returns a list of all Steam applications supported by the API
     * along with their app IDs and market availability status.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with supported Steam applications
     * 
     * @route GET /api/v1/steam/apps
     */
    public function getAppInfo(Request $request, Response $response): Response
    {
        $data = $this->steamService->getSupportedApps();
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * API status and health check
     * 
     * Provides API status information, version details, and available endpoints.
     * Used for monitoring API health and discovering available functionality.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with API status and endpoint information
     * 
     * @route GET /api/v1/steam/status
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
     * Find Steam application by name
     * 
     * Searches for Steam applications by name and returns matching results
     * with app IDs and market support information.
     *
     * @param Request $request The HTTP request object containing query parameters
     * @param Response $response The HTTP response object
     * @return Response JSON response with matching Steam applications or error message
     * 
     * @route GET /api/v1/steam/find-app
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
     * Get detailed Steam application information
     * 
     * Retrieves comprehensive information about a specific Steam application
     * including name, description, market support, and metadata.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @param array $args Route arguments containing appId
     * @return Response JSON response with detailed app information or error message
     * 
     * @route GET /api/v1/steam/app/{appId}
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
