<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use Exception;
use App\Helpers\ConfigHelper;

/**
 * Steam Profile Service
 * 
 * Handles Steam profile-related operations including profile data,
 * inventory management, friend lists, and other profile features.
 * Uses Steam Web API and community data endpoints.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class SteamProfileService
{
    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;
    
    /**
     * Steam Web API base URL
     * @var string
     */
    private string $steamApiUrl = 'https://api.steampowered.com';
    
    /**
     * Steam Community base URL
     * @var string
     */
    private string $steamCommunityUrl = 'https://steamcommunity.com';
    
    /**
     * Steam API key for authenticated requests
     * @var string|null
     */
    private ?string $apiKey;
    
    /**
     * SteamProfileService constructor
     * 
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->apiKey = ConfigHelper::steam('api_key');
        
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
            $vanityName = $this->extractVanityName($identifier);
            
            if (!$vanityName) {
                return null;
            }
            
            // Resolve vanity URL to Steam ID using Steam API
            return $this->resolveVanityUrl($vanityName);
            
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
    
    /**
     * Get Steam profile information
     * 
     * @param string $steamId Steam64 ID
     * @return array|null Profile data or null if not found
     */
    public function getProfile(string $steamId): ?array
    {
        try {
            if (!$this->apiKey) {
                return $this->getProfileFromCommunity($steamId);
            }
            
            $url = $this->steamApiUrl . '/ISteamUser/GetPlayerSummaries/v0002/';
            $params = [
                'key' => $this->apiKey,
                'steamids' => $steamId
            ];
            
            $response = $this->makeApiCall($url, $params);
            
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
                'personastate' => $this->getPersonaStateText($player['personastate'] ?? 0),
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
     * Get user's Steam inventory for a specific app
     * 
     * @param string $steamId Steam64 ID
     * @param int $appId Steam App ID (default: 730 for CS2)
     * @param int $contextId Context ID (default: 2 for items)
     * @return array|null Inventory data or null if not accessible
     */
    public function getInventory(string $steamId, int $appId = 730, int $contextId = 2): ?array
    {
        try {
            $url = $this->steamCommunityUrl . "/inventory/{$steamId}/{$appId}/{$contextId}";
            $params = [
                'l' => 'english',
                'count' => '75'  // Steam's default limit
            ];
            
            $response = $this->makeApiCall($url, $params, true); // Changed to true for JSON response
            
            if (!$response) {
                if ($this->logger) {
                    $this->logger->warning('Inventory API call failed', [
                        'steamid' => $steamId,
                        'appid' => $appId,
                        'url' => $url
                    ]);
                }
                return null;
            }
            
            // Check for specific error responses
            if (isset($response['success']) && $response['success'] === false) {
                return null;
            }
            
            if (!isset($response['assets']) || !isset($response['descriptions'])) {
                return null;
            }
            
            $items = [];
            $descriptions = $response['descriptions'] ?? [];
            
            // Create lookup table for descriptions
            $descriptionLookup = [];
            foreach ($descriptions as $desc) {
                $key = $desc['classid'] . '_' . $desc['instanceid'];
                $descriptionLookup[$key] = $desc;
            }
            
            // Process inventory items
            foreach ($response['assets'] as $asset) {
                $key = $asset['classid'] . '_' . $asset['instanceid'];
                $description = $descriptionLookup[$key] ?? null;
                
                if ($description) {
                    $items[] = [
                        'assetid' => $asset['assetid'],
                        'classid' => $asset['classid'],
                        'instanceid' => $asset['instanceid'],
                        'amount' => $asset['amount'],
                        'name' => $description['name'] ?? 'Unknown Item',
                        'market_name' => $description['market_name'] ?? null,
                        'market_hash_name' => $description['market_hash_name'] ?? null,
                        'tradable' => $description['tradable'] ?? 0,
                        'marketable' => $description['marketable'] ?? 0,
                        'commodity' => $description['commodity'] ?? 0,
                        'type' => $description['type'] ?? null,
                        'icon_url' => isset($description['icon_url']) ? 
                            'https://community.cloudflare.steamstatic.com/economy/image/' . $description['icon_url'] : null,
                        'icon_url_large' => isset($description['icon_url_large']) ? 
                            'https://community.cloudflare.steamstatic.com/economy/image/' . $description['icon_url_large'] : null,
                        'exterior' => $this->extractExterior($description['name'] ?? ''),
                        'rarity' => $this->extractRarity($description['tags'] ?? [])
                    ];
                }
            }
            
            return [
                'steamid' => $steamId,
                'appid' => $appId,
                'contextid' => $contextId,
                'total_inventory_count' => $response['total_inventory_count'] ?? count($items),
                'items' => $items,
                'success' => true
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get inventory', [
                    'steamid' => $steamId,
                    'appid' => $appId,
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
            
            $response = $this->makeApiCall($url, $params);
            
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
            
            $response = $this->makeApiCall($url, $params);
            
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
    
    /**
     * Extract vanity name from various URL formats
     * 
     * @param string $input URL or vanity name
     * @return string|null Vanity name or null
     */
    private function extractVanityName(string $input): ?string
    {
        // Direct vanity name (no special characters)
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $input) && strlen($input) <= 32) {
            return $input;
        }
        
        // Steam profile URL patterns
        $patterns = [
            '/steamcommunity\.com\/id\/([a-zA-Z0-9_-]+)\/?/',
            '/steamcommunity\.com\/profiles\/([0-9]+)\/?/' // This should be handled differently
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Resolve vanity URL to Steam ID using Steam API
     * 
     * @param string $vanityName Vanity URL name
     * @return string|null Steam64 ID or null
     */
    private function resolveVanityUrl(string $vanityName): ?string
    {
        try {
            if (!$this->apiKey) {
                // Try community XML method as fallback
                return $this->resolveVanityFromCommunity($vanityName);
            }
            
            $url = $this->steamApiUrl . '/ISteamUser/ResolveVanityURL/v0001/';
            $params = [
                'key' => $this->apiKey,
                'vanityurl' => $vanityName
            ];
            
            $response = $this->makeApiCall($url, $params);
            
            if ($response && isset($response['response']['steamid']) && $response['response']['success'] == 1) {
                return $response['response']['steamid'];
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to resolve vanity URL', [
                    'vanity_name' => $vanityName,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Fallback method to resolve vanity URL using community XML
     * 
     * @param string $vanityName Vanity URL name
     * @return string|null Steam64 ID or null
     */
    private function resolveVanityFromCommunity(string $vanityName): ?string
    {
        try {
            $url = $this->steamCommunityUrl . "/id/{$vanityName}/?xml=1";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Steam Market REST API'
                ]
            ]);
            
            $xmlData = file_get_contents($url, false, $context);
            
            if ($xmlData === false) {
                return null;
            }
            
            $xml = simplexml_load_string($xmlData);
            
            if ($xml && isset($xml->steamID64)) {
                return (string)$xml->steamID64;
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to resolve vanity from community XML', [
                    'vanity_name' => $vanityName,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get profile from Steam Community (fallback when no API key)
     * 
     * @param string $steamId Steam64 ID
     * @return array|null Basic profile data or null
     */
    private function getProfileFromCommunity(string $steamId): ?array
    {
        try {
            $url = $this->steamCommunityUrl . "/profiles/{$steamId}/?xml=1";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Steam Market REST API'
                ]
            ]);
            
            $xmlData = file_get_contents($url, false, $context);
            
            if ($xmlData === false) {
                return null;
            }
            
            $xml = simplexml_load_string($xmlData);
            
            if (!$xml) {
                return null;
            }
            
            return [
                'steamid' => (string)$xml->steamID64,
                'personaname' => (string)$xml->steamID,
                'profileurl' => (string)$xml->profileURL,
                'avatar' => (string)$xml->avatarIcon,
                'avatarmedium' => (string)$xml->avatarMedium,
                'avatarfull' => (string)$xml->avatarFull,
                'personastate' => 'Unknown (API key required)',
                'realname' => isset($xml->realname) ? (string)$xml->realname : null,
                'summary' => isset($xml->summary) ? (string)$xml->summary : null,
                'location' => isset($xml->location) ? (string)$xml->location : null,
                'limited_account' => true  // Community XML has limited data
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get profile from community', [
                    'steamid' => $steamId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Make API call to Steam endpoints
     * 
     * @param string $url URL to call
     * @param array $params Query parameters
     * @param bool $isJsonResponse Whether to expect JSON response (default: true)
     * @return array|null Response data or null on failure
     */
    private function makeApiCall(string $url, array $params = [], bool $isJsonResponse = true): ?array
    {
        try {
            $fullUrl = $url . '?' . http_build_query($params);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'method' => 'GET',
                    'header' => [
                        'Accept: application/json, text/plain, */*',
                        'Accept-Language: en-US,en;q=0.9',
                        'Cache-Control: no-cache'
                    ]
                ]
            ]);
            
            if ($this->logger) {
                $this->logger->debug('Making API call', [
                    'url' => $fullUrl,
                    'expect_json' => $isJsonResponse
                ]);
            }
            
            $response = file_get_contents($fullUrl, false, $context);
            
            if ($response === false) {
                if ($this->logger) {
                    $this->logger->warning('API call returned false', [
                        'url' => $fullUrl,
                        'http_response_header' => $http_response_header ?? null
                    ]);
                }
                return null;
            }
            
            if ($isJsonResponse) {
                $data = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if ($this->logger) {
                        $this->logger->error('JSON decode error', [
                            'url' => $fullUrl,
                            'json_error' => json_last_error_msg(),
                            'response_preview' => substr($response, 0, 200)
                        ]);
                    }
                    return null;
                }
                
                return $data;
            }
            
            return ['raw' => $response];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('API call failed', [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Convert persona state number to text
     * 
     * @param int $state Persona state number
     * @return string Human-readable status
     */
    private function getPersonaStateText(int $state): string
    {
        $states = [
            0 => 'Offline',
            1 => 'Online',
            2 => 'Busy',
            3 => 'Away',
            4 => 'Snooze',
            5 => 'Looking to trade',
            6 => 'Looking to play'
        ];
        
        return $states[$state] ?? 'Unknown';
    }
    
    /**
     * Extract item exterior from name
     * 
     * @param string $name Item name
     * @return string|null Exterior condition
     */
    private function extractExterior(string $name): ?string
    {
        $exteriors = [
            'Factory New', 'Minimal Wear', 'Field-Tested', 
            'Well-Worn', 'Battle-Scarred'
        ];
        
        foreach ($exteriors as $exterior) {
            if (strpos($name, "({$exterior})") !== false) {
                return $exterior;
            }
        }
        
        return null;
    }
    
    /**
     * Extract rarity from item tags
     * 
     * @param array $tags Item tags array
     * @return string|null Rarity level
     */
    private function extractRarity(array $tags): ?string
    {
        foreach ($tags as $tag) {
            if (isset($tag['category']) && $tag['category'] === 'Rarity') {
                return $tag['localized_tag_name'] ?? $tag['name'] ?? null;
            }
        }
        
        return null;
    }
}
