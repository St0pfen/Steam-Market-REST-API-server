<?php
declare(strict_types=1);

namespace App\Services;


use Psr\Log\LoggerInterface;
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
 * @author Stopfen
 * @version 1.0.0
 */
class SteamSocialService
{
    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

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
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->steamApiUrl = ConfigHelper::steam('api_url');
        $this->steamCommunityUrl = ConfigHelper::steam('community_url');
        $this->logger = $logger;
        $this->apiKey = ConfigHelper::steam('api_key');
        $this->socialHelper = new SocialServiceHelper($logger);
        $this->webApi = new SteamWebApiHelper($logger);
        
        if ($this->logger) {
            $this->logger->info('SteamProfileService initialized', [
                'has_api_key' => !empty($this->apiKey)
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
            
            $url = $this->steamApiUrl . '/ISteamUser/GetPlayerSummaries/v0002/';
            $params = [
                'key' => $this->apiKey,
                'steamids' => $steamId
            ];
            
            $response = $this->webApi->makeApiCall($url, $params);
            
            if (!$response || !isset($response['response']['players'][0])) {
                return null;
            }
            
            $player = $response['response']['players'][0];
            
            return [
                'steamid' => $player['steamid'],
                'personaname' => $player['personaname'] ?? 'Unknown',
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
                'loccountrycode' => $player['loccountrycode'] ?? null,
                'locstatecode' => $player['locstatecode'] ?? null
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