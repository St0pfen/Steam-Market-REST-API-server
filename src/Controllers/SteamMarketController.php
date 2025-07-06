<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Services\SteamMarketService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Steam Market Controller
 *
 * Handles endpoints related to retrieving item prices from the Steam Market.
 */
class SteamMarketController
{
    /**
     * Steam Market service instance for API calls
     * @var SteamMarketService
     */
    private SteamMarketService $steamMarketService;  

    /**
     * SteamMarketController constructor
     *
     * Instantiates the SteamMarketService internally.
     */
    public function __construct()
    {
        $this->steamMarketService = new SteamMarketService();
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
        $data = $this->steamMarketService->getItemPrice($itemName, $appId);

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

        $data = $this->steamMarketService->searchItems($query, $appId, $count);

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

        $data = $this->steamMarketService->getPopularItems($appId);

        $statusCode = $data['success'] ? 200 : 500;
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }


    //getCategories
    //@TODO FIRST IMPLEMENT OTHER CONTROLLERS AND SERVICES
}