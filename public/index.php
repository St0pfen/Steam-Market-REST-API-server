<?php
/**
 * Steam Market REST API - Main Entry Point
 * 
 * This file serves as the main entry point for the Steam Market REST API.
 * It initializes the Slim framework, configures middleware, sets up routing,
 * and handles the application bootstrap process.
 *
 * @package stopfen/steam-rest-api-php
 * @author @StopfMich
 * @version 1.0.0
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\IpLoggingMiddleware;
use App\Helpers\ConfigHelper;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create Slim application instance
$app = AppFactory::create();

// Add error middleware with configuration from environment
$app->addErrorMiddleware(ConfigHelper::app('debug'), true, true);

// Add IP Logging Middleware for request tracking
$app->add(new IpLoggingMiddleware());

// CORS Middleware using centralized configuration
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', ConfigHelper::cors('allow_origin'))
        ->withHeader('Access-Control-Allow-Headers', ConfigHelper::cors('allow_headers'))
        ->withHeader('Access-Control-Allow-Methods', ConfigHelper::cors('allow_methods'));
});

/**
 * Default route - API welcome endpoint
 * 
 * Provides basic API information and navigation links
 * for users accessing the root endpoint.
 */
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

// Load API routes from separate file
require __DIR__ . '/../src/Routes/api.php';

// Run the application
$app->run();