<?php
declare(strict_types=1);

namespace App\Services;

use SteamApi\SteamApi;
use Psr\Log\LoggerInterface;
use App\Helpers\LogHelper;

/**
 * SteamShopService
 *
 * Provides services related to Steam shop functionalities such as
 * searching for apps, retrieving app details, and more.
 */
class SteamShopService
{
 /**
     * Steam API client instance
     * @var SteamApi
     */
    private SteamApi $steamApi;
    
    /**
     * Optional logger instance for API call logging
     * @var LogHelper|null
     */
    private ?LogHelper $logger;
    
    /**
     * SteamMarketService constructor
     * 
     * Initializes the Steam API client with optional API key
     * and sets up logging if provided.
     *
     * @param LoggerInterface|null $logger Optional logger for API call tracking
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $apiKey = $_ENV['STEAM_API_KEY'] ?? null;
        
        if ($apiKey) {
            $this->steamApi = new SteamApi($apiKey);
        } else {
            // Without API key (limited functionality)
            $this->steamApi = new SteamApi();
        }
        
        $this->logger = $logger;
    }

    
    /**
     * Find Steam application by name
     * 
     * Searches for Steam applications matching the provided name
     * and returns relevant app IDs and information.
     *
     * @param string $appName Name of the Steam application to search for
     * @return array Search results with matching applications and metadata
     * @throws \Exception When API call fails or returns invalid data
     */
    public function findAppByName(string $appName): array
    {
        try {
            $this->logger->log('info', "Searching for app: {$appName}");

            // Use Steam Store API for app search
            $searchUrl = "https://store.steampowered.com/api/storesearch/?term=" . urlencode($appName) . "&l=english&cc=US";
            
            $response = file_get_contents($searchUrl);
            $data = json_decode($response, true);
            
            $apps = [];
            if ($data && isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    if ($item['type'] === 'app') {
                        $apps[] = [
                            'id' => (int)$item['id'],
                            'name' => $item['name'],
                            'type' => $item['type'],
                            'tiny_image' => $item['tiny_image'] ?? null,
                            'price' => $item['price']['final'] ?? null
                        ];
                    }
                }
            }
            
            return [
                'apps' => $apps,
                'search_term' => $appName,
                'count' => count($apps),
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->log('error', "Error finding app by name: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'search_term' => $appName,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }    

        
    /**
     * Get detailed Steam application information
     * 
     * Retrieves comprehensive information about a Steam application
     * including name, description, market support, and metadata.
     *
     * @param int $appId Steam application ID
     * @return array Detailed application information with success status
     * @throws \Exception When API call fails or returns invalid data
     */
    public function getAppDetails(int $appId): array
    {
        try {
            $this->logger->log('info', "Fetching app details for: {$appId}");

            // Steam Store API for app details
            $detailsUrl = "https://store.steampowered.com/api/appdetails?appids={$appId}&l=english";
            
            $response = file_get_contents($detailsUrl);
            $data = json_decode($response, true);
            
            if ($data && isset($data[$appId]) && $data[$appId]['success']) {
                $appData = $data[$appId]['data'];
                
                return [
                    'app_id' => $appId,
                    'name' => $appData['name'],
                    'type' => $appData['type'] ?? 'game',
                    'is_free' => $appData['is_free'] ?? false,
                    'description' => $appData['short_description'] ?? '',
                    'developers' => $appData['developers'] ?? [],
                    'publishers' => $appData['publishers'] ?? [],
                    'categories' => array_map(fn($cat) => $cat['description'], $appData['categories'] ?? []),
                    'genres' => array_map(fn($genre) => $genre['description'], $appData['genres'] ?? []),
                    'header_image' => $appData['header_image'] ?? null,
                    'has_market' => $this->checkMarketSupport($appId),
                    'success' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'error' => 'App not found or not available',
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->log('error', "Error fetching app details: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

            /**
     * Check if a Steam application has Market support
     * 
     * Tests whether the specified application supports Steam Market
     * transactions by attempting a test API call.
     *
     * @param int $appId Steam application ID to check
     * @return bool True if the app supports Steam Market, false otherwise
     */
    private function checkMarketSupport(int $appId): bool
    {
        try {
            // Test with a simple market request
            $testUrl = "https://steamcommunity.com/market/search/render/?appid={$appId}&norender=1&count=1";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; Steam Market API)'
                ]
            ]);
            $response = @file_get_contents($testUrl, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                return isset($data['total_count']) && $data['total_count'] > 0;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}