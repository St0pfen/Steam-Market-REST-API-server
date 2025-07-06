<?php
/**
 * API Routes Configuration
 * 
 * Defines all API routes for the Steam Market REST API including
 * general endpoints, Steam Market endpoints, and error handling.
 * Uses Slim Framework route groups for organization.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */

declare(strict_types=1);

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\ApiController;
use App\Controllers\SteamController;
use App\Controllers\SteamShopController;
use App\Controllers\SteamMarketController;
use App\Controllers\SteamSocialController;
use App\Controllers\SteamInventoryController;

// Main API Routes Group - Version 1
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // General API Endpoints
    $group->get('/test', [ApiController::class, 'test']);
    $group->get('/docs', [ApiController::class, 'getDocs']);

    // Steam Market API Endpoints
    $group->group('/steam', function (RouteCollectorProxy $steamGroup) {
        // Shop Endpoints
        $steamGroup->group('/shop', function (RouteCollectorProxy $shopGroup) {
            $shopGroup->get('/status', [SteamController::class, 'getStatus']);
            $shopGroup->get('/apps', [SteamController::class, 'getAppInfo']);
            $shopGroup->get('/find-app', [SteamShopController::class, 'findAppByName']);
            $shopGroup->get('/app/{appId}', [SteamShopController::class, 'getAppDetails']);
        });
        // Market Endpoints
        $steamGroup->group('/market', function (RouteCollectorProxy $marketGroup) {

            $marketGroup->get('/item/{itemName}', [SteamMarketController::class, 'getItemPrice']);
            $marketGroup->get('/search', [SteamMarketController::class, 'searchItems']);
            $marketGroup->get('/popular', [SteamMarketController::class, 'getPopularItems']);
            $marketGroup->get('/categories', [SteamMarketController::class, 'getCategories']); //todo
        });
        // Profile Endpoints
        $steamGroup->group('/profile', function (RouteCollectorProxy $profileGroup) {
            $profileGroup->get('/search', [SteamSocialController::class, 'searchProfiles']); //todo
            $profileGroup->get('/{identifier}', [SteamSocialController::class, 'getProfile']);
            $profileGroup->get('/friends/{identifier}', [SteamSocialController::class, 'getFriends']);
            $profileGroup->get('/level/{identifier}', [SteamSocialController::class, 'getProfileLevel']); //todo
            $profileGroup->get('/summary/{identifier}', [SteamSocialController::class, 'getProfileSummary']); //todo
            $profileGroup->get('/games/recent/{identifier}', [SteamSocialController::class, 'getRecentGames']);
        });
        $steamGroup->group('/inventory', function (RouteCollectorProxy $inventoryGroup) {
            $inventoryGroup->get('/{identifier}', [SteamInventoryController::class, 'getInventory']);
            $inventoryGroup->get('/value/{identifier}', [SteamInventoryController::class, 'getInventoryValue']); //todo
            $inventoryGroup->get('/trade-link/{identifier}', [SteamInventoryController::class, 'getTradeLink']); //todo
        });
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