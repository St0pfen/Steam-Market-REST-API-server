<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamSocialService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * Constructor
     * 
     * Instantiates the SteamSocialService internally.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->socialService = new SteamSocialService();
        if ($logger) {
            $this->logger = $logger;
        }
    }

    /**
     * Get Steam profile information
     * 
     * GET /api/v1/steam/profile/{identifier}
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with profile data
     */
    public function getProfile(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            
            if (empty($identifier)) {
                $data = [
                    'error' => 'Steam ID or profile identifier is required',
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Resolve Steam ID from various formats
            $steamId = $this->socialService->resolveSteamId($identifier);

            if (!$steamId) {
                $data = [
                    'error' => 'Steam profile not found or invalid identifier',
                    'identifier' => $identifier,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            // Get profile data
            $profileData = $this->socialService->getProfile($steamId);
            
            if (!$profileData) {
                $data = [
                    'error' => 'Profile not found or private',
                    'steamid' => $steamId,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'profile' => $profileData,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if ($this->logger) {
                $this->logger->info('Profile retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'personaname' => $profileData['personaname'] ?? 'Unknown'
                ]);
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Profile retrieval failed', [
                    'identifier' => $identifier ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error while retrieving profile',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get Steam profile friend list
     * 
     * GET /api/v1/steam/profile/{identifier}/friends
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with friends data
     */
    public function getFriends(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            
            if (empty($identifier)) {
                $data = [
                    'error' => 'Steam ID or profile identifier is required',
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Resolve Steam ID
            $steamId = $this->socialService->resolveSteamId($identifier);
            
            if (!$steamId) {
                $data = [
                    'error' => 'Steam profile not found',
                    'identifier' => $identifier,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            // Get friends data
            $friendsData = $this->socialService->getFriendList($steamId);
            
            if (!$friendsData) {
                $data = [
                    'error' => 'Friend list not accessible (private profile or API key required)',
                    'steamid' => $steamId,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'friends' => $friendsData,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if ($this->logger) {
                $this->logger->info('Friend list retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'friends_count' => $friendsData['friends_count'] ?? 0
                ]);
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Friend list retrieval failed', [
                    'identifier' => $identifier ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error while retrieving friend list',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get recently played games
     * 
     * GET /api/v1/steam/profile/{identifier}/games/recent
     * Query params: count (default: 10)
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with recent games data
     */
    public function getRecentGames(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            $queryParams = $request->getQueryParams();
            
            $count = min((int)($queryParams['count'] ?? 10), 50); // Max 50 games
            
            if (empty($identifier)) {
                $data = [
                    'error' => 'Steam ID or profile identifier is required',
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Resolve Steam ID
            $steamId = $this->socialService->resolveSteamId($identifier);
            
            if (!$steamId) {
                $data = [
                    'error' => 'Steam profile not found',
                    'identifier' => $identifier,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            // Get recent games data
            $gamesData = $this->socialService->getRecentlyPlayedGames($steamId, $count);
            
            if (!$gamesData) {
                $data = [
                    'error' => 'Recent games not accessible (API key required)',
                    'steamid' => $steamId,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'recent_games' => $gamesData,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if ($this->logger) {
                $this->logger->info('Recent games retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'games_count' => count($gamesData['games'] ?? [])
                ]);
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Recent games retrieval failed', [
                    'identifier' => $identifier ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error while retrieving recent games',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    //searchProfiles TODO
    //getProfileLevel TODO
    //getProfileSummary TODO
}
