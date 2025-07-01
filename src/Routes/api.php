<?php
declare(strict_types=1);

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\ApiController;
use App\Controllers\SteamController;

// Basis API Routes
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    
    // Allgemeine API Endpoints
    $group->get('/test', [ApiController::class, 'test']);
    $group->get('/docs', [ApiController::class, 'getDocs']);
    
    // Steam Market API Endpoints
    $group->group('/steam', function (RouteCollectorProxy $steamGroup) {
        
        // Status und Info Endpoints
        $steamGroup->get('/status', [SteamController::class, 'getStatus']);
        $steamGroup->get('/apps', [SteamController::class, 'getAppInfo']);
        
        // Dynamische App-Suche
        $steamGroup->get('/find-app', [SteamController::class, 'findAppByName']);
        $steamGroup->get('/app/{appId}', [SteamController::class, 'getAppDetails']);
        
        // Steam Market Data Endpoints
        $steamGroup->get('/item/{itemName}', [SteamController::class, 'getItemPrice']);
        $steamGroup->get('/search', [SteamController::class, 'searchItems']);
        $steamGroup->get('/popular', [SteamController::class, 'getPopularItems']);
    });
});

// 404 Handler
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