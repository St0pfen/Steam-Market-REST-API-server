<?php
declare(strict_types=1);

namespace App\Services;

use SteamApi\SteamApi;
use Psr\Log\LoggerInterface;

/**
 * Steam Market Service
 * 
 * Provides access to Steam Market API functionality including item pricing,
 * search capabilities, app information, and market data retrieval.
 * Handles API authentication and error management.
 *
 * @package stopfen/steam-rest-api-php
 * @author @StopfMich
 * @version 1.0.0
 */
class SteamMarketService
{
    /**
     * Steam API client instance
     * @var SteamApi
     */
    private SteamApi $steamApi;
    
    /**
     * Optional logger instance for API call logging
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;
    
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
     * Get Steam Market item pricing information
     * 
     * Retrieves detailed pricing data for a specific Steam Market item
     * including lowest price, median price, volume, and image URL.
     *
     * @param string $itemName The market hash name of the item
     * @param int $appId Steam application ID (default: 730 for CS:GO)
     * @return array Item pricing data with success status and timestamp
     * @throws \Exception When API call fails or returns invalid data
     */
    public function getItemPrice(string $itemName, int $appId = 730): array
    {
        try {
            $this->log('info', "Fetching price for item: {$itemName} (App: {$appId})");
            
            $options = [
                'market_hash_name' => $itemName,
                'country' => 'US',
                'currency' => 1
            ];
            
            $response = $this->steamApi->detailed()->getItemPricing($appId, $options);
            
            if ($response && isset($response['response'])) {
                $data = $response['response'];
                      // Since getItemPricing doesn't provide image URL, we try a search
            $imageUrl = $this->getItemImageFromMarket($itemName, $appId);
                
                $result = [
                    'item_name' => $itemName,
                    'lowest_price' => $data['lowest_price'] ?? null,
                    'lowest_price_str' => $data['lowest_price_str'] ?? null,
                    'volume' => $data['volume'] ?? null,
                    'median_price' => $data['median_price'] ?? null,
                    'median_price_str' => $data['median_price_str'] ?? null,
                    'image_url' => $imageUrl,
                    'app_id' => $appId,
                    'success' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                $result = [
                    'error' => 'No data received from Steam API',
                    'item_name' => $itemName,
                    'app_id' => $appId,
                    'success' => false,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            $this->log('info', "Successfully fetched price for: {$itemName}");
            return $result;
            
        } catch (\Exception $e) {
            $this->log('error', "Error fetching item price: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'item_name' => $itemName,
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Search for Steam Market items
     * 
     * Performs a search query against the Steam Market API to find
     * items matching the search criteria with pagination support.
     *
     * @param string $query Search query string
     * @param int $appId Steam application ID to search within (default: 730)
     * @param int $count Maximum number of results to return (default: 10, max: 100)
     * @return array Search results with items array and metadata
     * @throws \Exception When API call fails or returns invalid data
     */
    public function searchItems(string $query, int $appId = 730, int $count = 10): array
    {
        try {
            $this->log('info', "Searching for items: {$query} (App: {$appId}, Count: {$count})");
            
            $options = [
                'query' => $query,
                'start' => 0,
                'count' => min($count, 100), // API limit
                'search_descriptions' => false
            ];
            
            $response = $this->steamApi->detailed()->searchItems($appId, $options);
            
            $items = [];
            if ($response && isset($response['response']) && isset($response['response']['results'])) {
                foreach ($response['response']['results'] as $item) {
                    // Extract image URL from item data
                    $imageUrl = $this->extractImageFromItem($item);
                    
                    $items[] = [
                        'name' => $item['name'] ?? 'Unknown',
                        'hash_name' => $item['hash_name'] ?? '',
                        'sell_price' => $item['sell_price'] ?? null,
                        'sell_price_text' => $item['sell_price_text'] ?? '',
                        'sell_listings' => $item['sell_listings'] ?? 0,
                        'buy_price' => $item['buy_price'] ?? null,
                        'buy_price_text' => $item['buy_price_text'] ?? '',
                        'image_url' => $imageUrl,
                        'app_id' => $appId
                    ];
                }
            }
            
            $this->log('info', "Found " . count($items) . " items for query: {$query}");
            
            return [
                'items' => $items,
                'query' => $query,
                'app_id' => $appId,
                'count' => count($items),
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->log('error', "Error searching items: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'query' => $query,
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Get popular Steam Market items for an application
     * 
     * Retrieves trending and popular items from the Steam Market
     * for the specified application ID.
     *
     * @param int $appId Steam application ID (default: 730 for CS:GO)
     * @return array Popular items array with metadata and success status
     * @throws \Exception When API call fails or returns invalid data
     */
    public function getPopularItems(int $appId = 730): array
    {
        try {
            $this->log('info', "Fetching popular items for app: {$appId}");
            
            // Since the API has no direct "Popular Items" function,
            // we search for popular items with an empty query
            $options = [
                'query' => '',
                'start' => 0,
                'count' => 20,
                'search_descriptions' => false
            ];
            
            $response = $this->steamApi->detailed()->searchItems($appId, $options);
            
            $popularItems = [];
            if ($response && isset($response['response']) && isset($response['response']['results'])) {
                foreach ($response['response']['results'] as $item) {
                    // Extract image URL from item data
                    $imageUrl = $this->extractImageFromItem($item);
                    
                    $popularItems[] = [
                        'name' => $item['name'] ?? 'Unknown',
                        'hash_name' => $item['hash_name'] ?? '',
                        'sell_price' => $item['sell_price'] ?? null,
                        'sell_price_text' => $item['sell_price_text'] ?? '',
                        'image_url' => $imageUrl,
                        'app_id' => $appId
                    ];
                }
            }
            
            $this->log('info', "Found " . count($popularItems) . " popular items");
            
            return [
                'items' => $popularItems,
                'app_id' => $appId,
                'count' => count($popularItems),
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->log('error', "Error fetching popular items: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
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
            $this->log('info', "Searching for app: {$appName}");
            
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
            $this->log('error', "Error finding app by name: " . $e->getMessage());
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
            $this->log('info', "Fetching app details for: {$appId}");
            
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
            $this->log('error', "Error fetching app details: " . $e->getMessage());
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

    /**
     * Get list of supported Steam applications
     * 
     * Returns a curated list of Steam applications that support
     * the Market API with their names and descriptions.
     *
     * @return array Array of supported applications with app IDs as keys
     */
    public function getSupportedApps(): array
    {
        $staticApps = [
            730 => [
                'name' => 'Counter-Strike 2',
                'description' => 'CS2 Items and Skins',
                'has_market' => true,
                'verified' => true
            ],
            570 => [
                'name' => 'Dota 2',
                'description' => 'Dota 2 Items and Cosmetics',
                'has_market' => true,
                'verified' => true
            ],
            440 => [
                'name' => 'Team Fortress 2',
                'description' => 'TF2 Items and Hats',
                'has_market' => true,
                'verified' => true
            ],
            252490 => [
                'name' => 'Rust',
                'description' => 'Rust Items and Skins',
                'has_market' => true,
                'verified' => true
            ],
            304930 => [
                'name' => 'Unturned',
                'description' => 'Unturned Items',
                'has_market' => true,
                'verified' => true
            ]
        ];

        return [
            'apps' => $staticApps,
            'default_app_id' => 730,
            'note' => 'Use /api/v1/steam/find-app?name={app_name} to find other Steam apps',
            'dynamic_search' => true,
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Build full Steam Community image URL from icon fragment
     * 
     * Constructs a complete image URL using Steam Community CDN
     * from a partial icon URL path.
     *
     * @param string|null $iconUrl Partial icon URL or null
     * @return string|null Complete Steam Community image URL or null
     */
    private function buildImageUrl(?string $iconUrl): ?string
    {
        if (empty($iconUrl)) {
            return null;
        }
        
        // Steam CDN base URL
        $baseUrl = 'https://community.cloudflare.steamstatic.com/economy/image/';
        
        // If URL is already complete, return it
        if (str_starts_with($iconUrl, 'http')) {
            return $iconUrl;
        }
        
        return $baseUrl . $iconUrl;
    }
    
    /**
     * Get item image URL from Steam Market using market hash name
     * 
     * Alternative method to retrieve item images by searching
     * the Steam Market for the specific item name.
     *
     * @param string $marketHashName The market hash name of the item
     * @param int $appId Steam application ID
     * @return string|null Item image URL or null if not found
     */
    private function getItemImageFromMarket(string $marketHashName, int $appId): ?string
    {
        try {
            // Use the Steam Market Listing API to get more details
            $url = "https://steamcommunity.com/market/listings/{$appId}/" . urlencode($marketHashName);
            
            // For now we use the search function as fallback
            $searchOptions = [
                'query' => $marketHashName,
                'start' => 0,
                'count' => 1,
                'search_descriptions' => false
            ];
            
            $searchResponse = $this->steamApi->detailed()->searchItems($appId, $searchOptions);
            if ($searchResponse && isset($searchResponse['response']['results'][0])) {
                $item = $searchResponse['response']['results'][0];
                if (isset($item['asset_description']['icon_url'])) {
                    return $this->buildImageUrl($item['asset_description']['icon_url']);
                }
            }
            
            return null;
        } catch (\Exception $e) {
            $this->log('warning', "Could not fetch item image from market: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract image URL from item data array
     * 
     * Searches through various possible fields in item data
     * to find and extract the item's image URL.
     *
     * @param array $item Item data array from Steam API
     * @return string|null Item image URL or null if not found
     */
    private function extractImageFromItem(array $item): ?string
    {
        // Check various fields where image URLs can be found
        $imageFields = [
            'asset_description.icon_url_large',
            'asset_description.icon_url', 
            'icon_url_large',
            'icon_url'
        ];
        
        foreach ($imageFields as $field) {
            $value = $this->getNestedValue($item, $field);
            if ($value) {
                return $this->buildImageUrl($value);
            }
        }
        
        return null;
    }
    
    /**
     * Retrieve nested array value using dot notation
     * 
     * Helper method to safely extract values from nested arrays
     * using a dot-separated path string.
     *
     * @param array $array Array to search in
     * @param string $path Dot-separated path to the desired value
     * @return mixed Found value or null if path doesn't exist
     */
    private function getNestedValue(array $array, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $array;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }
    
    /**
     * Log message using configured logger
     * 
     * Safely logs messages through the configured logger instance
     * if one is available.
     *
     * @param string $level Log level (info, warning, error, etc.)
     * @param string $message Message to log
     * @return void
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message);
        }
    }
}
