<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamSocialService;
use App\Services\LoggerService;
use App\Helpers\ResponseHelper;
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
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamSocialController
{
    private ?LoggerService $logger = null;
    private SteamSocialService $socialService;

    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger ?? new LoggerService();
        $this->socialService = new SteamSocialService();
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
     * Get Steam profile information.
     */
    public function getProfile(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            if (empty($identifier)) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam ID or profile identifier is required', 'success' => false], 400);
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam profile not found or invalid identifier', 'success' => false], 404);
            }
            $profileData = $this->socialService->getProfile($steamId);
            if (!$profileData) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Profile not found or private', 'success' => false], 404);
            }
            return ResponseHelper::jsonResponse($response, ['profile' => $profileData, 'success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
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
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam ID or profile identifier is required', 'success' => false], 400);
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam profile not found', 'success' => false], 404);
            }
            $friendsData = $this->socialService->getFriendList($steamId);
            if (!$friendsData) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Friend list not accessible (private profile or API key required)', 'success' => false], 404);
            }
            return ResponseHelper::jsonResponse($response, ['friends' => $friendsData, 'success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
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
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam ID or profile identifier is required', 'success' => false], 400);
            }
            $steamId = $this->socialService->resolveSteamId($identifier);
            if (!$steamId) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Steam profile not found', 'success' => false], 404);
            }
            $gamesData = $this->socialService->getRecentlyPlayedGames($steamId, $count);
            if (!$gamesData) {
                return ResponseHelper::jsonResponse($response, ['error' => 'Recent games not accessible (API key required)', 'success' => false], 404);
            }
            return ResponseHelper::jsonResponse($response, ['recent_games' => $gamesData, 'success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e);
        }
    }
}
