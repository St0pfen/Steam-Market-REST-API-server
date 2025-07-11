<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamInventoryService;
use App\Services\SteamSocialService;
use App\Services\SteamMarketService;
use App\Services\LoggerService;
use App\Helpers\SteamWebApiHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * SteamInventoryController
 *
 * Handles Steam inventory-related endpoints: fetch inventory and filter for highest value items.
 * All comments and names are in English for clarity and maintainability.
 */
class SteamInventoryController
{
    private SteamInventoryService $inventoryService;
    private SteamSocialService $socialService;
    private SteamMarketService $marketService;
    private ?LoggerService $logger;
    private SteamWebApiHelper $webApi;

    /**
     * Constructor initializes all required services.
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->webApi = new SteamWebApiHelper($this->logger);
        $this->inventoryService = new SteamInventoryService($this->logger, $this->webApi);
        $this->socialService = new SteamSocialService($this->logger, $this->webApi);
        $this->marketService = new SteamMarketService($this->logger, $this->webApi);
    }

    /**
     * Get Steam profile inventory.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with inventory data or error
     */
    public function getInventory(Request $request, Response $response, array $args): Response
    {
        $identifier = $args['identifier'] ?? '';
        $appId = isset($args['appId']) ? (int)$args['appId'] : (int)($request->getQueryParams()['app_id'] ?? 730);
        $contextId = isset($args['contextId']) ? (int)$args['contextId'] : (int)($request->getQueryParams()['context_id'] ?? 2);

        if (empty($identifier)) {
            return $this->jsonError($response, 'Steam ID or profile identifier is required', 400);
        }

        $steamId = $this->socialService->resolveSteamId($identifier);
        if (!$steamId) {
            return $this->jsonError($response, 'Steam profile not found', 404, ['identifier' => $identifier]);
        }

        $inventoryData = $this->inventoryService->getInventory($steamId, $appId, $contextId);
        if (!$inventoryData) {
            return $this->jsonError($response, 'Inventory not accessible or empty', 404);
        }

        $result = [
            'inventory' => $inventoryData,
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get highest value (Covert) items in inventory, including market data.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with only Covert items and their market data
     */
    public function getInventoryHighestValue(Request $request, Response $response, array $args): Response
    {
        $identifier = $args['identifier'] ?? '';
        $appId = isset($request->getQueryParams()['app_id']) && (int)$request->getQueryParams()['app_id'] > 0
            ? (int)$request->getQueryParams()['app_id'] : 730;
        $contextId = isset($request->getQueryParams()['context_id']) ? (int)$request->getQueryParams()['context_id'] : 2;

        if (empty($identifier)) {
            return $this->jsonError($response, 'Steam ID or profile identifier is required', 400);
        }

        $steamId = $this->socialService->resolveSteamId($identifier);
        if (!$steamId) {
            return $this->jsonError($response, 'Steam profile not found', 404, ['identifier' => $identifier]);
        }

        $inventoryData = $this->inventoryService->getInventory($steamId, $appId, $contextId);
        if (!$inventoryData || !isset($inventoryData['items']) || !is_array($inventoryData['items'])) {
            return $this->jsonError($response, 'Inventory not accessible or empty', 404);
        }

        $covertItems = [];
        foreach ($inventoryData['items'] as $item) {
            if (isset($item['rarity']) && $item['rarity'] === 'Covert') {
                $item['market_data'] = $this->marketService->getItemPrice($item['market_hash_name'], $appId);
                $covertItems[] = $item;
            }
        }

        $result = [
            'covert_items' => $covertItems,
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper to return a formatted JSON error response.
     *
     * @param Response $response
     * @param string $message
     * @param int $status
     * @param array $extra
     * @return Response
     */
    private function jsonError(Response $response, string $message, int $status, array $extra = []): Response
    {
        $data = array_merge([
            'error' => $message,
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ], $extra);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}