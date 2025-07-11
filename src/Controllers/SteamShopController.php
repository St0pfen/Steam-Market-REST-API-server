<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\LoggerService;
use App\Services\SteamShopService as SteamShopService;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * SteamShopController
 *
 * Handles endpoints related to searching for Steam apps by name.
 * Provides functionality to find apps by name and retrieve detailed app information.
 * 
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamShopController
{
    private ?LoggerService $logger = null;
    private SteamShopService $steamShopService;

    /**
     * Constructor
     * 
     * Initializes the SteamShopService and optional logger.
     *
     * @param LoggerService|null $logger Optional logger for request logging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->steamShopService = new SteamShopService($this->logger);
    }

    private function jsonError(Response $response, \Throwable $e): Response
    {
        if ($this->logger) {
            $this->logger->error('Internal server error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        return ResponseHelper::jsonResponse($response, ['success' => false], 500);
    }

    /**
     * Find Steam application by name
     * 
     * Searches for Steam applications by name and returns matching results
     * with app IDs and market support information.
     *
     * @param Request $request The HTTP request object containing query parameters
     * @param Response $response The HTTP response object
     * @return Response JSON response with matching Steam applications or error message
     * 
     * @route GET /api/v1/steam/find-app
     */
    public function findAppByName(Request $request, Response $response, array $args): Response
    {
        try {
            $appName = urldecode($args['app-name'] ?? '');
            if (empty($appName)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'App name is required', 'success' => false], 400);
            }
            $data = $this->steamShopService->findAppByName($appName);
            return ResponseHelper::jsonResponse($response, $data);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }

    /**
     * Get detailed Steam application information
     * 
     * Retrieves comprehensive information about a specific Steam application
     * including name, description, market support, and metadata.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @param array $args Route arguments containing appId
     * @return Response JSON response with detailed app information or error message
     * 
     * @route GET /api/v1/steam/app/{appId}
     */
    public function getAppDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $appId = (int)($args['appId'] ?? 0);
            if ($appId <= 0) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Valid app ID is required', 'success' => false], 400);
            }
            $data = $this->steamShopService->getAppDetails($appId);
            $statusCode = $data['success'] ? 200 : 404;
            return ResponseHelper::jsonResponse($response, $data, $statusCode);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}