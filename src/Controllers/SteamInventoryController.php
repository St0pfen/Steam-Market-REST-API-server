<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamInventoryService;
use App\Services\LoggerService;
use Psr\Log\NullLogger;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\SteamSocialService;

use App\Helpers\SteamWebApiHelper;

/**
 * Steam Inventory Service
 *
 * Handles Steam inventory-related operations including fetching inventory,
 * calculating total value, and generating trade links.
 *
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamInventoryController
{
    /**
     * Profile service for resolving Steam IDs and fetching profile data
     * @var SteamInventoryService
     */
    private SteamInventoryService $inventoryService;

    /**
     * Social service for fetching user social data
     * @var SteamSocialService
     */
    private SteamSocialService $socialService;

    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerService|null
     */
    private ?LoggerService $logger = null;

    /**
     * Helper for Steam Web API operations
     * @var SteamWebApiHelper
     */
    private SteamWebApiHelper $webApi;

    /**
     * SteamInventoryController constructor
     * @param LoggerService|null $logger Optional logger for debugging
     * @param SteamWebApiHelper $webApi Helper for Steam Web API operations
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->webApi = new SteamWebApiHelper($this->logger);
        $this->inventoryService = new SteamInventoryService($this->logger, $this->webApi);
        $this->socialService = new SteamSocialService($this->logger, $this->webApi);
    }

    /**
     * Get Steam profile inventory
     * 
     * GET /api/v1/steam/profile/{identifier}/inventory
     * Query params: app_id (default: 730), context_id (default: 2)
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with inventory data
     */
    public function getInventory(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            // Prefer appId from route args, then query param, then default 730
            $appId = isset($args['appId']) ? (int)$args['appId'] : (int)($request->getQueryParams()['app_id'] ?? 730);
            $queryParams = $request->getQueryParams();
            $contextId = (int)($queryParams['context_id'] ?? 2); // Default to items context
            
            if (empty($identifier)) {
                $data = [
                    'error' => 'Steam ID or profile identifier is required',
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Resolve Steam ID
            $steamId = $this->socialService->resolveSteamId($identifier);

            if (!$steamId) {
                $data = [
                    'error' => 'Steam profile not found',
                    'identifier' => $identifier,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            // Get inventory data
            $inventoryData = $this->inventoryService->getInventory($steamId, $appId, $contextId);

            if (!$inventoryData) {
                // Check profile privacy as this might be the issue
                $profileData = $this->socialService->getProfile($steamId);
                $privacyInfo = '';
                
                if ($profileData && isset($profileData['communityvisibilitystate'])) {
                    $visibilityState = $profileData['communityvisibilitystate'];
                    switch ($visibilityState) {
                        case 1:
                            $privacyInfo = ' (Profile is private)';
                            break;
                        case 2:
                            $privacyInfo = ' (Profile is friends only)';
                            break;
                        case 3:
                            $privacyInfo = ' (Profile is public but inventory may be private)';
                            break;
                        default:
                            $privacyInfo = ' (Unknown privacy state)';
                    }
                }
                
                $data = [
                    'error' => 'Inventory not accessible' . $privacyInfo,
                    'details' => [
                        'steamid' => $steamId,
                        'appid' => $appId,
                        'context_id' => $contextId,
                        'possible_reasons' => [
                            'Inventory privacy settings',
                            'No items in this game',
                            'Invalid app ID or context ID',
                            'Steam API rate limiting',
                            'Temporary Steam service issues'
                        ]
                    ],
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'inventory' => $inventoryData,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if ($this->logger) {
                $this->logger->info('Inventory retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'appid' => $appId,
                    'item_count' => count($inventoryData['items'] ?? [])
                ]);
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Inventory retrieval failed', [
                    'identifier' => $identifier ?? 'unknown',
                    'appid' => $appId ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error while retrieving inventory',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    /**
     * Get highest value items in inventory
     * Filtered by app_id and item rarity
     *
     * GET /api/v1/steam/profile/{identifier}/inventory/highest-value
     * Query params: app_id (default: 730), context_id (default: 2)
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with total value data
     */
#    public function getInventoryHighestValue(Request $request, Response $response, array $args): Response
#    {
#        try {
#            $inventory = $this->getInventory($request, $response, $args);
#            
#        }
#    }
}