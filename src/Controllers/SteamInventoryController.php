<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamInventoryService;
use App\Services\SteamSocialService;
use App\Services\SteamMarketService;
use App\Services\LoggerService;
use App\Helpers\SteamWebApiHelper;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * SteamInventoryController
 *
 * Handles Steam inventory-related endpoints: fetch inventory and filter for highest value items.
 * All comments and names are in English for clarity and maintainability.
 * 
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamInventoryController
{
    private ?LoggerService $logger = null;
    private SteamInventoryService $inventoryService;
    private SteamSocialService $socialService;
    private SteamMarketService $marketService;
    private SteamWebApiHelper $webApi;

    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->webApi = new SteamWebApiHelper($this->logger);
        $this->inventoryService = new SteamInventoryService($this->logger, $this->webApi);
        $this->socialService = new SteamSocialService($this->logger);
        $this->marketService = new SteamMarketService($this->logger, $this->webApi);
    }

    private function jsonError(Response $response, \Throwable $e): Response
    {
        if ($this->logger) {
            $this->logger->error('Internal server error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        return ResponseHelper::jsonResponse($response, ['success' => false], 500);
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
        try {
            $identifier = $args['identifier'] ?? '';
            $appId = isset($args['appId']) ? (int)$args['appId'] : (int)($request->getQueryParams()['app_id'] ?? 730);
            $contextId = isset($args['contextId']) ? (int)$args['contextId'] : (int)($request->getQueryParams()['context_id'] ?? 2);
            if (empty($identifier)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam ID or profile identifier is required', 'success' => false], 400);
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam profile not found', 'success' => false], 404);
            }
            $inventoryData = $this->inventoryService->getInventory($steamId, $appId, $contextId);
            if (!$inventoryData) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Inventory not accessible or empty', 'success' => false], 404);
            }
            return ResponseHelper::jsonResponse($response, ['inventory' => $inventoryData, 'success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
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
        try {
            $identifier = $args['identifier'] ?? '';
            $appId = isset($request->getQueryParams()['app_id']) && (int)$request->getQueryParams()['app_id'] > 0
                ? (int)$request->getQueryParams()['app_id'] : 730;
            $contextId = isset($request->getQueryParams()['context_id']) ? (int)$request->getQueryParams()['context_id'] : 2;
            if (empty($identifier)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam ID or profile identifier is required', 'success' => false], 400);
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam profile not found', 'success' => false], 404);
            }
            $inventoryData = $this->inventoryService->getInventory($steamId, $appId, $contextId);
            if (!$inventoryData || !isset($inventoryData['items']) || !is_array($inventoryData['items'])) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Inventory not accessible or empty', 'success' => false], 404);
            }
            $covertItems = [];
            foreach ($inventoryData['items'] as $item) {
                if (isset($item['rarity']) && $item['rarity'] === 'Covert') {
                    $item['market_data'] = $this->marketService->getItemPrice($item['market_hash_name'], $appId);
                    $covertItems[] = $item;
                }
            }
            return ResponseHelper::jsonResponse($response, ['covert_items' => $covertItems, 'success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}