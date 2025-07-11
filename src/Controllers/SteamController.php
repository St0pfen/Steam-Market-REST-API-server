<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamMarketService;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
     * @var LoggerService|null
     */
    private ?LoggerService $logger;
    
    /**
     * SteamController constructor
     * 
     * Initializes the controller with optional logger and creates
     * a new instance of SteamMarketService for handling API calls.
     *
     * @param LoggerService|null $logger Optional logger instance for request logging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->steamService = new SteamMarketService($this->logger);
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
        $data = [
            'apps' => [
                 730 => [
                    'name' => 'Counter-Strike 2',
                    'description' => 'CS2 Items and Skins',
                    'has_market' => true,
                    'verified' => true
                ],
                570 => [
                    'name' => 'Dota 2',
                    'description' => 'Dota 2 Items and Cosmetics',
                    'has_market' => true,
                    'verified' => true
                ],
                440 => [
                    'name' => 'Team Fortress 2',
                    'description' => 'TF2 Items and Hats',
                    'has_market' => true,
                    'verified' => true
                ],
                252490 => [
                    'name' => 'Rust',
                    'description' => 'Rust Items and Skins',
                    'has_market' => true,
                    'verified' => true
                ],
                304930 => [
                    'name' => 'Unturned',
                    'description' => 'Unturned Items',
                    'has_market' => true,
                    'verified' => true
                ]
            ],
            'default_app' => 730,
            'note' => 'Use /api/v1/steam/find-app?name={app_name} to find other Steam apps',
            'dynamic_search' => true,
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
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
}
