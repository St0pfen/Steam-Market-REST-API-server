<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SteamProfileService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Steam Profile Controller
 * 
 * Handles Steam profile-related endpoints including profile information,
 * inventory access, friend lists, and other profile features.
 * Integrates with Steam Web API through the SteamProfileService.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class ProfileController
{
    /**
     * Steam Profile service instance for API calls
     * @var SteamProfileService
     */
    private SteamProfileService $profileService;
    
    /**
     * Optional logger instance for request logging
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;
    
    /**
     * ProfileController constructor
     * 
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->profileService = new SteamProfileService($logger);
        $this->logger = $logger;
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
            $steamId = $this->profileService->resolveSteamId($identifier);
            
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
            $profileData = $this->profileService->getProfile($steamId);
            
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
     * Get Steam profile inventory
     * 
     * GET /api/v1/steam/profile/{identifier}/inventory
     * Query params: app_id (default: 730), context_id (default: 2)
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with inventory data
     */
    public function getInventory(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['identifier'] ?? '';
            $queryParams = $request->getQueryParams();
            
            $appId = (int)($queryParams['app_id'] ?? 730); // Default to CS2
            $contextId = (int)($queryParams['context_id'] ?? 2); // Default to items context
            
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
            $steamId = $this->profileService->resolveSteamId($identifier);
            
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
            
            // Get inventory data
            $inventoryData = $this->profileService->getInventory($steamId, $appId, $contextId);
            
            if (!$inventoryData) {
                // Check profile privacy as this might be the issue
                $profileData = $this->profileService->getProfile($steamId);
                $privacyInfo = '';
                
                if ($profileData && isset($profileData['communityvisibilitystate'])) {
                    $visibilityState = $profileData['communityvisibilitystate'];
                    switch ($visibilityState) {
                        case 1:
                            $privacyInfo = ' (Profile is private)';
                            break;
                        case 2:
                            $privacyInfo = ' (Profile is friends only)';
                            break;
                        case 3:
                            $privacyInfo = ' (Profile is public but inventory may be private)';
                            break;
                        default:
                            $privacyInfo = ' (Unknown privacy state)';
                    }
                }
                
                $data = [
                    'error' => 'Inventory not accessible' . $privacyInfo,
                    'details' => [
                        'steamid' => $steamId,
                        'appid' => $appId,
                        'context_id' => $contextId,
                        'possible_reasons' => [
                            'Inventory privacy settings',
                            'No items in this game',
                            'Invalid app ID or context ID',
                            'Steam API rate limiting',
                            'Temporary Steam service issues'
                        ]
                    ],
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $responseData = [
                'inventory' => $inventoryData,
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if ($this->logger) {
                $this->logger->info('Inventory retrieved successfully', [
                    'identifier' => $identifier,
                    'steamid' => $steamId,
                    'appid' => $appId,
                    'item_count' => count($inventoryData['items'] ?? [])
                ]);
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Inventory retrieval failed', [
                    'identifier' => $identifier ?? 'unknown',
                    'appid' => $appId ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error while retrieving inventory',
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
            $steamId = $this->profileService->resolveSteamId($identifier);
            
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
            $friendsData = $this->profileService->getFriendList($steamId);
            
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
            $steamId = $this->profileService->resolveSteamId($identifier);
            
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
            $gamesData = $this->profileService->getRecentlyPlayedGames($steamId, $count);
            
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
    
    /**
     * Search Steam profiles by name
     * 
     * GET /api/v1/steam/profile/search
     * Query params: q (search query), limit (default: 10)
     * 
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response JSON response with search results
     */
    public function searchProfiles(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = $queryParams['q'] ?? '';
            $limit = min((int)($queryParams['limit'] ?? 10), 25); // Max 25 results
            
            if (empty($query) || strlen($query) < 3) {
                $data = [
                    'error' => 'Search query must be at least 3 characters long',
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Note: Steam doesn't provide a direct profile search API
            // This would require implementing a custom solution or using third-party services
            $data = [
                'error' => 'Profile search not implemented',
                'message' => 'Steam does not provide a public profile search API. Use exact Steam ID or profile URL instead.',
                'suggestion' => 'Try using /api/v1/steam/profile/{steamid_or_vanity_url}',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withStatus(501)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Profile search failed', [
                    'query' => $query ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'error' => 'Internal server error during profile search',
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
