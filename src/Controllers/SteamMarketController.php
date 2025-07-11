<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Services\SteamMarketService;
use App\Services\LoggerService;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Steam Market Controller
 *
 * Handles endpoints related to retrieving item prices from the Steam Market.
 * Provides functionality to get item prices, search for items, and retrieve popular items.
 * 
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamMarketController
{
    private ?LoggerService $logger = null;
    private SteamMarketService $steamMarketService;

    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->steamMarketService = new SteamMarketService($this->logger);
    }

    private function jsonError(Response $response, \Throwable $e): Response
    {
        if ($this->logger) {
            $this->logger->error('Internal Server Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        return ResponseHelper::jsonResponse($response, ['success' => false], 500);
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
        try {
            $itemName = urldecode($args['itemName'] ?? '');
            $appId = (int)($request->getQueryParams()['app_id'] ?? 730);
            if (empty($itemName)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Item name is required', 'success' => false], 400);
            }
            $data = $this->steamMarketService->getItemPrice($itemName, $appId);
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
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
    public function searchItems(Request $request, Response $response, array $args): Response
    {
        try {
            $itemName = urldecode($args['itemName'] ?? '');
            $appId = (int)($request->getQueryParams()['app_id'] ?? 730);
            $count = min((int)($request->getQueryParams()['count'] ?? 10), 50);
            if (empty($itemName)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Search query parameter "itemName" is required', 'success' => false], 400);
            }
            $data = $this->steamMarketService->searchItems($itemName, $appId, $count);
            $statusCode = $data['success'] ? 200 : 500;
            return ResponseHelper::jsonResponse($response, $data, $statusCode);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
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
        try {
            $appId = (int)($request->getQueryParams()['app_id'] ?? 730);
            $data = $this->steamMarketService->getPopularItems($appId);
            $statusCode = $data['success'] ? 200 : 500;
            return ResponseHelper::jsonResponse($response, $data, $statusCode);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}