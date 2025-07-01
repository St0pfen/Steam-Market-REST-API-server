<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ConfigHelper;

/**
 * API Controller
 * 
 * Handles general API endpoints including test endpoints and documentation.
 * Provides basic API functionality and generates dynamic API documentation.
 *
 * @package App\Controllers
 * @author Steam REST API
 * @version 1.0.0
 */
class ApiController
{
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
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', ConfigHelper::app('content_type'));
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
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        if ($request->getUri()->getPort()) {
            $baseUrl .= ':' . $request->getUri()->getPort();
        }
        
        $apiPrefix = ConfigHelper::app('api_prefix');
        
        $data = [
            'title' => ConfigHelper::app('name') . ' Documentation',
            'version' => ConfigHelper::app('version'),
            'base_url' => $baseUrl,
            'endpoints' => [
                'steam' => [
                    "GET /{$apiPrefix}/steam/status" => [
                        'description' => 'API status and health check',
                        'parameters' => []
                    ],
                    "GET /{$apiPrefix}/steam/apps" => [
                        'description' => 'List of all supported Steam apps',
                        'parameters' => []
                    ],
                    "GET /{$apiPrefix}/steam/find-app" => [
                        'description' => 'Search Steam apps by name',
                        'parameters' => [
                            'name' => 'App name to search for (required)'
                        ],
                        'example' => "/{$apiPrefix}/steam/find-app?name=Counter-Strike"
                    ],
                    "GET /{$apiPrefix}/steam/app/{appId}" => [
                        'description' => 'Detailed information about a Steam app',
                        'parameters' => [
                            'appId' => 'Steam App ID (required)'
                        ],
                        'example' => "/{$apiPrefix}/steam/app/730"
                    ],
                    "GET /{$apiPrefix}/steam/item/{itemName}" => [
                        'description' => 'Price and details of a specific item',
                        'parameters' => [
                            'itemName' => 'Item name (URL encoded)',
                            'app_id' => 'Steam App ID (optional, default: 730)'
                        ],
                        'example' => "/{$apiPrefix}/steam/item/AK-47%20%7C%20Redline%20(Field-Tested)?app_id=730"
                    ],
                    "GET /{$apiPrefix}/steam/search" => [
                        'description' => 'Search for items',
                        'parameters' => [
                            'q' => 'Search query (required)',
                            'app_id' => 'Steam App ID (optional, default: 730)',
                            'count' => 'Number of results (optional, default: 10, max: 50)'
                        ],
                        'example' => "/{$apiPrefix}/steam/search?q=AK-47&app_id=730&count=5"
                    ],
                    "GET /{$apiPrefix}/steam/popular" => [
                        'description' => 'Popular items for an app',
                        'parameters' => [
                            'app_id' => 'Steam App ID (optional, default: 730)'
                        ],
                        'example' => "/{$apiPrefix}/steam/popular?app_id=730"
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
                'Market support verification',
                'Robust error handling'
            ],
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', ConfigHelper::app('content_type'));
    }
}