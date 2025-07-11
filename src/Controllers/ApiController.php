<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ConfigHelper;
use App\Helpers\ResponseHelper;

/**
 * API Controller
 * 
 * Handles general API endpoints including test endpoints and documentation.
 * Provides basic API functionality and generates dynamic API documentation.
 *
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class ApiController
{
    /**
     * Write a minimal JSON error response (HTTP 500, no details to client).
     */
    private function jsonError(Response $response, \Throwable $e): Response
    {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        return ResponseHelper::jsonResponse($response, ['success' => false], 500);
    }

    /**
     * Basic API test endpoint
     * 
     * Provides a simple health check and basic API information.
     * Returns API status, version information, and available endpoints.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with API status and information
     * 
     * @route GET /api/v1/test
     */
    public function test(Request $request, Response $response): Response
    {
        try {
            $data = [
                'message' => ConfigHelper::app('name') . ' is working!',
                'version' => ConfigHelper::app('version'),
                'endpoints' => [
                    'Steam API' => '/' . ConfigHelper::app('api_prefix') . '/steam/',
                    'Documentation' => '/' . ConfigHelper::app('api_prefix') . '/docs'
                ],
                'timestamp' => date('Y-m-d H:i:s'),
                'success' => true
            ];
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }

    /**
     * API Documentation endpoint
     *
     * Generates comprehensive API documentation including all available endpoints,
     * parameters, examples, and supported Steam applications. Dynamically builds
     * documentation based on current configuration and available routes.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with complete API documentation
     *
     * @route GET /api/v1/docs
     */
    public function getDocs(Request $request, Response $response): Response
    {
        try {
            $uri = $request->getUri();
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '');
            $apiPrefix = ConfigHelper::app('api_prefix');
            $data = [
                'title' => ConfigHelper::app('name') . ' Documentation',
                'version' => ConfigHelper::app('version'),
                'base_url' => $baseUrl,
                'endpoints' => [
                    'steam' => [
                        "GET /{$apiPrefix}/steam/market/item/{itemName}" => [
                            'description' => 'Price and details of a specific item',
                            'parameters' => [
                                'itemName' => 'Item name (URL encoded)',
                                'app_id' => 'Steam App ID (optional, default: 730)'
                            ],
                            'example' => "/{$apiPrefix}/steam/market/item/AK-47%20%7C%20Redline%20(Field-Tested)?app_id=730"
                        ],
                        "GET /{$apiPrefix}/steam/market/search/{itemName}" => [
                            'description' => 'Search for items by name',
                            'parameters' => [
                                'itemName' => 'Item name (URL encoded)',
                                'app_id' => 'Steam App ID (optional, default: 730)'
                            ],
                            'example' => "/{$apiPrefix}/steam/market/search/AK-47?app_id=730"
                        ],
                        "GET /{$apiPrefix}/steam/market/popular" => [
                            'description' => 'Popular items for an app',
                            'parameters' => [
                                'app_id' => 'Steam App ID (optional, default: 730)'
                            ],
                            'example' => "/{$apiPrefix}/steam/market/popular?app_id=730"
                        ]
                    ],
                    'shop' => [
                        "GET /{$apiPrefix}/steam/shop/status" => [
                            'description' => 'API status and health check',
                            'parameters' => []
                        ],
                        "GET /{$apiPrefix}/steam/shop/apps" => [
                            'description' => 'List of all supported Steam apps',
                            'parameters' => []
                        ],
                        "GET /{$apiPrefix}/steam/shop/find-app/{app-name}" => [
                            'description' => 'Search Steam apps by name',
                            'parameters' => [
                                'app-name' => 'App name to search for (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/shop/find-app/Counter-Strike"
                        ],
                        "GET /{$apiPrefix}/steam/shop/app/{appId}" => [
                            'description' => 'Detailed information about a Steam app',
                            'parameters' => [
                                'appId' => 'Steam App ID (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/shop/app/730"
                        ]
                    ],
                    'profile' => [
                        "GET /{$apiPrefix}/steam/profile/{identifier}" => [
                            'description' => 'Get Steam profile information',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/profile/76561198000000000"
                        ],
                        "GET /{$apiPrefix}/steam/profile/summary/{identifier}" => [
                            'description' => 'Get summary information for a Steam profile (alias for getProfile)',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/profile/summary/76561198000000000"
                        ],
                        "GET /{$apiPrefix}/steam/profile/friends/{identifier}" => [
                            'description' => 'Get Steam profile friend list (requires API key)',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/profile/friends/76561198000000000"
                        ],
                        "GET /{$apiPrefix}/steam/profile/recent-games/{identifier}" => [
                            'description' => 'Get recently played games (requires API key)',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)',
                                'count' => 'Number of games to return (optional, default: 10, max: 50)'
                            ],
                            'example' => "/{$apiPrefix}/steam/profile/recent-games/76561198000000000?count=5"
                        ]
                    ],
                    'inventory' => [
                        "GET /{$apiPrefix}/steam/inventory/cs2/{identifier}" => [
                            'description' => 'Get CS2 inventory for a profile',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/inventory/cs2/76561198000000000"
                        ],
                        "GET /{$apiPrefix}/steam/inventory/{appId}/{identifier}" => [
                            'description' => 'Get inventory for a profile and app',
                            'parameters' => [
                                'appId' => 'Steam App ID (required)',
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/inventory/730/76561198000000000"
                        ],
                        "GET /{$apiPrefix}/steam/inventory/highest-value/{identifier}" => [
                            'description' => 'Get highest value inventory for a profile (TODO)',
                            'parameters' => [
                                'identifier' => 'Steam64 ID, custom URL, or profile URL (required)'
                            ],
                            'example' => "/{$apiPrefix}/steam/inventory/highest-value/76561198000000000"
                        ]
                    ]
                ],
                'common_app_ids' => [
                    730 => 'Counter-Strike: Global Offensive',
                    570 => 'Dota 2',
                    440 => 'Team Fortress 2',
                    252490 => 'Rust',
                    304930 => 'Unturned'
                ],
                'response_format' => [
                    'success' => 'true/false',
                    'timestamp' => 'ISO timestamp',
                    'data' => 'Response data or error message',
                    'image_url' => 'Steam CDN Image URL (for items)'
                ],
                'features' => [
                    'Item prices with full Steam CDN image URLs',
                    'Steam app search by name',
                    'Dynamic app detection',
                    'Steam profile information and data',
                    'Inventory browsing with item details',
                    'Friend list access (with API key)',
                    'Recently played games (with API key)',
                    'Market support verification',
                    'Robust error handling'
                ],
                'authentication' => [
                    'note' => 'Some features require a Steam Web API key',
                    'api_key_url' => 'https://steamcommunity.com/dev/apikey',
                    'required_for' => [
                        'Friend lists',
                        'Recently played games',
                        'Enhanced profile data',
                        'Vanity URL resolution (fallback available)'
                    ]
                ],
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}