<?php
declare(strict_types=1);

namespace App\Helpers;

use Exception;
use Psr\Log\LoggerInterface;
use App\Helpers\SteamWebApiHelper;
/**
 * SocialServiceHelper Class
 *
 * Provides utility methods for handling social service operations,
 * such as profile management, friend lists, and inventory handling.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class SocialServiceHelper
{
    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * Helper for social service operations
     * @var SteamWebApiHelper
     */
    private ?SteamWebApiHelper $webApi;

    /**
     * Steam API key for authenticated requests
     * @var string|null
     */
    private ?string $apiKey;
    
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
     * SocialServiceHelper constructor
     * 
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerInterface $logger = null)
    {

        $this->logger = $logger;
        $this->apiKey = ConfigHelper::steam('api_key');
        $this->webApi = new SteamWebApiHelper($logger);
    }



    /**
     * Extract vanity name from various URL formats
     * 
     * @param string $input URL or vanity name
     * @return string|null Vanity name or null
     */
    public function extractVanityName(string $input): ?string
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
    public function resolveVanityUrl(string $vanityName): ?string
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
            
            $response = $this->webApi->makeApiCall($url, $params);
            
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
    public function getProfileFromCommunity(string $steamId): ?array
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
     * Convert persona state number to text
     * 
     * @param int $state Persona state number
     * @return string Human-readable status
     */
    public function getPersonaStateText(int $state): string
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
}