<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamShopService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * SteamShopController
 *
 * Handles endpoints related to searching for Steam apps by name.
 */
class SteamShopController
{
    /**
     * SteamShopService instance for handling shop-related operations
     * @var SteamShopService
     */
    private SteamShopService $steamService;
    /**
     * Optional logger instance for request logging
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * Constructor
     * 
     * Initializes the SteamShopService and optional logger.
     *
     * @param SteamShopService $steamService The service for handling Steam shop operations
     * @param LoggerInterface|null $logger Optional logger for request logging
     */
    public function __construct(SteamShopService $steamService, ?LoggerInterface $logger = null)
    {
        $this->steamService = $steamService;
        $this->logger = $logger;
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
    public function findAppByName(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        // Query parameters are automatically decoded
        $appName = $queryParams['name'] ?? '';
        
        if (empty($appName)) {
            $data = ['error' => 'App name is required', 'success' => false];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $data = $this->steamService->findAppByName($appName);
        
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
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
        $appId = (int)($args['appId'] ?? 0);
        
        if ($appId <= 0) {
            $data = [
                'error' => 'Valid app ID is required',
                'example' => '/api/v1/steam/app/730',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $data = $this->steamService->getAppDetails($appId);
        
        $statusCode = $data['success'] ? 200 : 404;
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}