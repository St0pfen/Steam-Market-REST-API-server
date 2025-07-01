<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\IpLoggingMiddleware;
use App\Helpers\ConfigHelper;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(ConfigHelper::app('debug'), true, true);

// Add IP Logging Middleware
$app->add(new IpLoggingMiddleware());

// CORS Middleware using ConfigHelper
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', ConfigHelper::cors('allow_origin'))
        ->withHeader('Access-Control-Allow-Headers', ConfigHelper::cors('allow_headers'))
        ->withHeader('Access-Control-Allow-Methods', ConfigHelper::cors('allow_methods'));
});

// Default route
$app->get('/', function (Request $request, Response $response) {
    $data = [
        'message' => 'Welcome to ' . ConfigHelper::app('name'),
        'version' => ConfigHelper::app('version'),
        'api_endpoint' => '/' . ConfigHelper::app('api_prefix'),
        'documentation' => '/' . ConfigHelper::app('api_prefix') . '/docs',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', ConfigHelper::app('content_type'));
});

// Load API routes
require __DIR__ . '/../src/Routes/api.php';

$app->run();