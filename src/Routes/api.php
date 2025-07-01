<?php
/**
 * API Routes Configuration
 * 
 * Defines all API routes for the Steam Market REST API including
 * general endpoints, Steam Market endpoints, and error handling.
 * Uses Slim Framework route groups for organization.
 *
 * @package Steam REST API
 * @author Steam REST API
 * @version 1.0.0
 */

declare(strict_types=1);

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\ApiController;
use App\Controllers\SteamController;

// Main API Routes Group - Version 1
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    
    // General API Endpoints
    $group->get('/test', [ApiController::class, 'test']);
    $group->get('/docs', [ApiController::class, 'getDocs']);
    
    // Steam Market API Endpoints
    $group->group('/steam', function (RouteCollectorProxy $steamGroup) {
        
        // Status and Information Endpoints
        $steamGroup->get('/status', [SteamController::class, 'getStatus']);
        $steamGroup->get('/apps', [SteamController::class, 'getAppInfo']);
        
        // Dynamic App Search Endpoints
        $steamGroup->get('/find-app', [SteamController::class, 'findAppByName']);
        $steamGroup->get('/app/{appId}', [SteamController::class, 'getAppDetails']);
        
        // Steam Market Data Endpoints
        $steamGroup->get('/item/{itemName}', [SteamController::class, 'getItemPrice']);
        $steamGroup->get('/search', [SteamController::class, 'searchItems']);
        $steamGroup->get('/popular', [SteamController::class, 'getPopularItems']);
    });
});

// Global 404 Error Handler
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    $data = [
        'error' => 'Endpoint not found',
        'requested_path' => $request->getUri()->getPath(),
        'available_endpoints' => [
            'GET /' => 'API Info',
            'GET /api/v1/docs' => 'API Documentation', 
            'GET /api/v1/test' => 'API Test',
            'GET /api/v1/steam/status' => 'Steam API Status',
            'GET /api/v1/steam/apps' => 'Supported Steam Apps'
        ],
        'success' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
});