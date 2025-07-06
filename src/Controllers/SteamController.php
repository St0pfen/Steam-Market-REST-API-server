<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamMarketService;
use App\Helpers\LogHelper;
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
     * @var LogHelper|null
     */
    private ?LogHelper $logger;
    
    /**
     * SteamController constructor
     * 
     * Initializes the controller with optional logger and creates
     * a new instance of SteamMarketService for handling API calls.
     *
     * @param LogHelper|null $logger Optional logger instance for request logging
     */
    public function __construct(?LogHelper $logger = null)
    {
        $this->steamService = new SteamMarketService($logger);
        $this->logger = $logger;
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
}
