<?php
declare(strict_types=1);

namespace App\Services;


use App\Services\LoggerService;
use Exception;
use App\Helpers\ConfigHelper;
use App\Helpers\SocialServiceHelper;
use App\Helpers\SteamWebApiHelper;


/**
 * Steam Social Service
 * 
 * Handles Steam social-related operations including profile data,
 * inventory management, friend lists, and other social features.
 * Uses Steam Web API and community data endpoints.
 *
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamSocialService
{
    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerService|null
     */
    private ?LoggerService $logger;

    /**
     * Helper for social service operations
     * @var SocialServiceHelper
     */
    private SocialServiceHelper $socialHelper;
    /**
     * Steam Web API base URL
     * @var string $steamApiUrl
     * @var string $steamCommunityUrl
     */
    private string $steamApiUrl;
    private string $steamCommunityUrl;

    /**
     * Steam API key for authenticated requests
     * @var string|null
     */
    private ?string $apiKey;

    /**
     * Steam API function from SteamWebApiHelper
     * @var SteamWebApiHelper
     */
    private SteamWebApiHelper $webApi;

    /**
     * SteamProfileService constructor
     * 
     * @param LoggerService|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $apiUrl = ConfigHelper::steam('api_url');
        $communityUrl = ConfigHelper::steam('community_url');
        $apiKey = ConfigHelper::steam('api_key');

        // Handle missing config gracefully
        $this->steamApiUrl = is_string($apiUrl) ? $apiUrl : '';
        $this->steamCommunityUrl = is_string($communityUrl) ? $communityUrl : '';
        $this->apiKey = is_string($apiKey) ? $apiKey : null;
        $this->logger = $logger;
        $this->socialHelper = new SocialServiceHelper($logger);
        $this->webApi = new SteamWebApiHelper($logger);
        if ($this->logger) {
            $this->logger->info('SteamProfileService initialized', [
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Resolve Steam ID from various input formats
     * 
     * Accepts Steam64 ID, custom URL, or profile URL and returns Steam64 ID
     *
     * @param string $identifier Steam ID, custom URL, or profile URL
     * @return string|null Steam64 ID or null if not found
     */
    public function resolveSteamId(string $identifier): ?string
    {
        try {
            // If it's already a Steam64 ID (17 digits starting with 765)
            if (preg_match('/^765\d{14}$/', $identifier)) {
                return $identifier;
            }
            
            // If it's a custom URL or profile URL, extract vanity name
            $vanityName = $this->socialHelper->extractVanityName($identifier);

            if (!$vanityName) {
                return null;
            }
            
            // Resolve vanity URL to Steam ID using Steam API
            return $this->socialHelper->resolveVanityUrl($vanityName);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to resolve Steam ID', [
                    'identifier' => $identifier,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    public function getProfile(string $steamId): ?array
    {
        try {
            if (!$this->apiKey) {
                return $this->socialHelper->getProfileFromCommunity($steamId);
            }

            // Player summaries requires 'steamids' (comma-separated)
            $paramsSummaries = [
                'key' => $this->apiKey,
                'steamids' => $steamId
            ];
            // Player level requires 'steamid' (single ID)
            $paramsLevel = [
                'key' => $this->apiKey,
                'steamid' => $steamId
            ];
            // Owned games requires 'steamid' (single ID)
            $paramsOwnedGames = [
                'key' => $this->apiKey,
                'steamid' => $steamId
            ];

            $urlPlayerSummaries = $this->steamApiUrl . '/ISteamUser/GetPlayerSummaries/v0002/';
            $urlPlayerLevels = $this->steamApiUrl . '/IPlayerService/GetSteamLevel/v1/';
            $urlPlayerOwnerGames = $this->steamApiUrl . '/IPlayerService/GetOwnedGames/v1/';

            $responsePlayerSummaries = $this->webApi->makeApiCall($urlPlayerSummaries, $paramsSummaries);
            $responsePlayerLevels = $this->webApi->makeApiCall($urlPlayerLevels, $paramsLevel);
            $responsePlayerOwnedGames = $this->webApi->makeApiCall($urlPlayerOwnerGames, $paramsOwnedGames);

            if (!$responsePlayerSummaries || !isset($responsePlayerSummaries['response']['players'][0])) {
                return null;
            }
            if (!$responsePlayerLevels || !isset($responsePlayerLevels['response']['player_level'])) {
                $responsePlayerLevels['response']['player_level'] = 0; // Default level if not found
            }

            if (!$responsePlayerOwnedGames || !isset($responsePlayerOwnedGames['response']['games'])) {
                $responsePlayerOwnedGames['response']['games'] = []; // Default to empty array if not found
            }

            $player = $responsePlayerSummaries['response']['players'][0];
            $player['level'] = $responsePlayerLevels['response']['player_level'];
            $player['games'] = $responsePlayerOwnedGames['response']['games'];

            
            return [
                'steamid' => $player['steamid'],
                'personaname' => $player['personaname'] ?? 'Unknown',
                'level' => $player['level'] ?? 0,
                'games_count' => count($player['games'] ?? []),
                'profileurl' => $player['profileurl'] ?? null,
                'avatar' => $player['avatar'] ?? null,
                'avatarmedium' => $player['avatarmedium'] ?? null,
                'avatarfull' => $player['avatarfull'] ?? null,
                'personastate' => $this->socialHelper->getPersonaStateText($player['personastate'] ?? 0),
                'communityvisibilitystate' => $player['communityvisibilitystate'] ?? 1,
                'profilestate' => $player['profilestate'] ?? 0,
                'lastlogoff' => isset($player['lastlogoff']) ? date('Y-m-d H:i:s', $player['lastlogoff']) : null,
                'realname' => $player['realname'] ?? null,
                'primaryclanid' => $player['primaryclanid'] ?? null,
                'timecreated' => isset($player['timecreated']) ? date('Y-m-d H:i:s', $player['timecreated']) : null,
                'gameid' => $player['gameid'] ?? null,
                'gameextrainfo' => $player['gameextrainfo'] ?? null,
                'loccountrycode' => $player['loccountrycode'] ?? null
                #'locstatecode' => $player['locstatecode'] ?? null,
                #'all_games' => $player['games'] ?? []
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get profile', [
                    'steamid' => $steamId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }


        /**
     * Get user's friend list
     * 
     * @param string $steamId Steam64 ID
     * @return array|null Friends data or null if not accessible
     */
    public function getFriendList(string $steamId): ?array
    {
        try {
            if (!$this->apiKey) {
                throw new Exception('Steam API key required for friend list access');
            }
            
            $url = $this->steamApiUrl . '/ISteamUser/GetFriendList/v0001/';
            $params = [
                'key' => $this->apiKey,
                'steamid' => $steamId,
                'relationship' => 'friend'
            ];
            
            $response = $this->webApi->makeApiCall($url, $params);
            
            if (!$response || !isset($response['friendslist']['friends'])) {
                return null;
            }
            
            $friends = [];
            foreach ($response['friendslist']['friends'] as $friend) {
                $friends[] = [
                    'steamid' => $friend['steamid'],
                    'relationship' => $friend['relationship'],
                    'friend_since' => isset($friend['friend_since']) ? 
                        date('Y-m-d H:i:s', $friend['friend_since']) : null
                ];
            }
            
            return [
                'steamid' => $steamId,
                'friends_count' => count($friends),
                'friends' => $friends,
                'success' => true
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get friend list', [
                    'steamid' => $steamId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Get user's recently played games
     * 
     * @param string $steamId Steam64 ID
     * @param int $count Number of games to return (default: 10)
     * @return array|null Games data or null if not accessible
     */
    public function getRecentlyPlayedGames(string $steamId, int $count = 10): ?array
    {
        try {
            if (!$this->apiKey) {
                throw new Exception('Steam API key required for recently played games');
            }
            
            $url = $this->steamApiUrl . '/IPlayerService/GetRecentlyPlayedGames/v0001/';
            $params = [
                'key' => $this->apiKey,
                'steamid' => $steamId,
                'count' => $count
            ];
            
            $response = $this->webApi->makeApiCall($url, $params);
            
            if (!$response || !isset($response['response']['games'])) {
                return null;
            }
            
            $games = [];
            foreach ($response['response']['games'] as $game) {
                $games[] = [
                    'appid' => $game['appid'],
                    'name' => $game['name'],
                    'playtime_2weeks' => $game['playtime_2weeks'] ?? 0,
                    'playtime_forever' => $game['playtime_forever'] ?? 0,
                    'img_icon_url' => isset($game['img_icon_url']) ? 
                        "https://media.steampowered.com/steamcommunity/public/images/apps/{$game['appid']}/{$game['img_icon_url']}.jpg" : null,
                    'img_logo_url' => isset($game['img_logo_url']) ? 
                        "https://media.steampowered.com/steamcommunity/public/images/apps/{$game['appid']}/{$game['img_logo_url']}.jpg" : null
                ];
            }
            
            return [
                'steamid' => $steamId,
                'total_count' => $response['response']['total_count'] ?? count($games),
                'games' => $games,
                'success' => true
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get recently played games', [
                    'steamid' => $steamId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
}