<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamMarketService;
use App\Services\LoggerService;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Steam Controller
 * 
 * Handles all Steam Market API related endpoints including item pricing,
 * search functionality, app information, and market data retrieval.
 * Integrates with Steam Market API through the SteamMarketService.
 *
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamController
{
    /**
     * Optional logger instance for request logging
     * @var LoggerService|null
     */
    private ?LoggerService $logger = null;

    /**
     * Steam Market service instance for API calls
     * @var SteamMarketService
     */
    private SteamMarketService $steamService;
    
    /**
     * SteamController constructor
     * 
     * Initializes the controller with optional logger and creates
     * a new instance of SteamMarketService for handling API calls.
     *
     * @param LoggerService|null $logger Optional logger instance for request logging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->steamService = new SteamMarketService($this->logger);
    }
    
    /**
     * Helper to write a JSON error response (HTTP 500, no details to client)
     * Logs error details internally.
     *
     * @param Response $response
     * @param \Throwable $e
     * @return Response
     */
    private function jsonError(Response $response, \Throwable $e): Response
    {
        if ($this->logger) {
            $this->logger->error('Internal Server Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        return ResponseHelper::jsonResponse($response, ['success' => false], 500);
    }

    /**
     * Get supported Steam applications
     * 
     * Returns a list of all Steam applications supported by the API
     * along with their app IDs and market availability status.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with supported Steam applications
     * 
     * @route GET /api/v1/steam/apps
     */
    public function getAppInfo(Request $request, Response $response): Response
    {
        try {
            $data = [
                'apps' => [
                    730 => ['name' => 'Counter-Strike 2', 'description' => 'CS2 Items and Skins', 'has_market' => true, 'verified' => true],
                    570 => ['name' => 'Dota 2', 'description' => 'Dota 2 Items and Cosmetics', 'has_market' => true, 'verified' => true],
                    440 => ['name' => 'Team Fortress 2', 'description' => 'TF2 Items and Hats', 'has_market' => true, 'verified' => true],
                    252490 => ['name' => 'Rust', 'description' => 'Rust Items and Skins', 'has_market' => true, 'verified' => true],
                    304930 => ['name' => 'Unturned', 'description' => 'Unturned Items', 'has_market' => true, 'verified' => true]
                ],
                'default_app' => 730,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }

    /**
     * API status and health check
     * 
     * Provides API status information, version details, and available endpoints.
     * Used for monitoring API health and discovering available functionality.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @return Response JSON response with API status and endpoint information
     * 
     * @route GET /api/v1/steam/status
     */
    public function getStatus(Request $request, Response $response): Response
    {
        try {
            $data = [
                'status' => 'online',
                'service' => 'Steam Market API Wrapper',
                'version' => '1.0.0',
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}
