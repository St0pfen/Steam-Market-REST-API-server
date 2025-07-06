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
use App\Controllers\ProfileController;
use App\Controllers\SteamShopController;
use App\Controllers\SteamMarketController;
use App\Controllers\SteamSocialController;
use App\Controllers\ToolsController;

// Main API Routes Group - Version 1
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // General API Endpoints
    $group->get('/test', [ApiController::class, 'test']);
    $group->get('/docs', [ApiController::class, 'getDocs']);

    // Steam Market API Endpoints
    $group->group('/steam', function (RouteCollectorProxy $steamGroup) {
        // Market Endpoints
        $steamGroup->group('/market', function (RouteCollectorProxy $marketGroup) {

            $marketGroup->get('/item/{itemName}', [SteamMarketController::class, 'getItemPrice']);
            $marketGroup->get('/search', [SteamMarketController::class, 'searchItems']);
            $marketGroup->get('/popular', [SteamMarketController::class, 'getPopularItems']);
            $marketGroup->get('/trending', [SteamMarketController::class, 'getTrendingItems']);
            $marketGroup->get('/categories', [SteamMarketController::class, 'getCategories']);
        });
        // Shop Endpoints
        $steamGroup->group('/shop', function (RouteCollectorProxy $shopGroup) {
            $shopGroup->get('/status', [SteamController::class, 'getStatus']);
            $shopGroup->get('/apps', [SteamController::class, 'getAppInfo']);
            $shopGroup->get('/find-app', [SteamShopController::class, 'findAppByName']);
            $shopGroup->get('/app/{appId}', [SteamShopController::class, 'getAppDetails']);
        });
        // Profile Endpoints
        $steamGroup->group('/profile', function (RouteCollectorProxy $profileGroup) {
            $profileGroup->get('/search', [SteamSocialController::class, 'searchProfiles']);
            $profileGroup->get('/{identifier}', [SteamSocialController::class, 'getProfile']);
            $profileGroup->get('/friends/{identifier}', [SteamSocialController::class, 'getFriends']);
            $profileGroup->get('/level/{identifier}', [SteamSocialController::class, 'getProfileLevel']);
            $profileGroup->get('/summary/{identifier}', [SteamSocialController::class, 'getProfileSummary']);
            $profileGroup->get('/games/recent/{identifier}', [SteamSocialController::class, 'getRecentGames']);
            $profileGroup->get('/trade-link/{identifier}', [SteamSocialController::class, 'getTradeLink']);
            $profileGroup->get('/inventory/{identifier}', [SteamSocialController::class, 'getInventory']);
        });
        // Tools Endpoints
        $steamGroup->group('/tools', function (RouteCollectorProxy $toolsGroup) {
            $toolsGroup->get('/inventory-value', [ToolsController::class, 'getInventoryValue']);
            $toolsGroup->get('/vac-banned-friends', [ToolsController::class, 'getVacBannedFriends']);


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