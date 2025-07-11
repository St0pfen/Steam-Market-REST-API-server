<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamSocialService;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Steam Profile Controller
 * 
 * Handles Steam profile-related endpoints including profile information,
 * friend lists, and other profile features.
 * Integrates with Steam Web API through the SteamSocialService.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class SteamSocialController
{
    /**
     * Steam Profile service instance for API calls
     * @var SteamSocialService
     */
    private SteamSocialService $socialService;
    /**
     * Optional logger instance for request logging
     * @var LoggerService|null
     */
    private ?LoggerService $logger = null;

    /**
     * Constructor
     * 
     * Instantiates the SteamSocialService internally.
     * 
     * @param LoggerService|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->socialService = new SteamSocialService();
        if ($logger) {
            $this->logger = $logger;
        }
    }

    /**
     * Helper to write a JSON response with status code
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper for error responses
     */
    private function errorResponse(Response $response, string $error, int $status = 400, array $extra = []): Response
    {
        $data = array_merge([
            'error' => $error,
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ], $extra);
        return $this->jsonResponse($response, $data, $status);
    }

    /**
     * Helper for success responses
     */
    private function successResponse(Response $response, array $payload): Response
    {
        $data = array_merge($payload, [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return $this->jsonResponse($response, $data);
    }

    /**
     * Get Steam profile information.
     */
    public function getProfile(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            if (empty($identifier)) {
                return $this->errorResponse($response, 'Steam ID or profile identifier is required');
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return $this->errorResponse($response, 'Steam profile not found or invalid identifier', 404, ['identifier' => $identifier]);
            }
            $profileData = $this->socialService->getProfile($steamId);
            if (!$profileData) {
                return $this->errorResponse($response, 'Profile not found or private', 404, ['steamid' => $steamId]);
            }
            if ($this->logger) {
                $this->logger->info('Profile retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'personaname' => $profileData['personaname'] ?? 'Unknown'
                ]);
            }
            return $this->successResponse($response, ['profile' => $profileData]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Profile retrieval failed', [
                    'identifier' => $args['identifier'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            return $this->errorResponse($response, 'Internal server error while retrieving profile', 500);
        }
    }

    /**
     * Get Steam profile friend list.
     */
    public function getFriends(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            if (empty($identifier)) {
                return $this->errorResponse($response, 'Steam ID or profile identifier is required');
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return $this->errorResponse($response, 'Steam profile not found', 404, ['identifier' => $identifier]);
            }
            $friendsData = $this->socialService->getFriendList($steamId);
            if (!$friendsData) {
                return $this->errorResponse($response, 'Friend list not accessible (private profile or API key required)', 404, ['steamid' => $steamId]);
            }
            if ($this->logger) {
                $this->logger->info('Friend list retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'friends_count' => $friendsData['friends_count'] ?? 0
                ]);
            }
            return $this->successResponse($response, ['friends' => $friendsData]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Friend list retrieval failed', [
                    'identifier' => $args['identifier'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            return $this->errorResponse($response, 'Internal server error while retrieving friend list', 500);
        }
    }

    /**
     * Get recently played games.
     */
    public function getRecentGames(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            $count = min((int)($request->getQueryParams()['count'] ?? 10), 50);
            if (empty($identifier)) {
                return $this->errorResponse($response, 'Steam ID or profile identifier is required');
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return $this->errorResponse($response, 'Steam profile not found', 404, ['identifier' => $identifier]);
            }
            $gamesData = $this->socialService->getRecentlyPlayedGames($steamId, $count);
            if (!$gamesData) {
                return $this->errorResponse($response, 'Recent games not accessible (API key required)', 404, ['steamid' => $steamId]);
            }
            if ($this->logger) {
                $this->logger->info('Recent games retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'games_count' => count($gamesData['games'] ?? [])
                ]);
            }
            return $this->successResponse($response, ['recent_games' => $gamesData]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Recent games retrieval failed', [
                    'identifier' => $args['identifier'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            return $this->errorResponse($response, 'Internal server error while retrieving recent games', 500);
        }
    }
}
