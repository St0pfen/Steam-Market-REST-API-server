<?php
declare(strict_types=1);

namespace App\Services;

use SteamApi\SteamApi;
use App\Helpers\SteamImageHelper;
use App\Helpers\LogHelper;
use App\Services\LoggerService;

/**
 * Steam Market Service
 * 
 * Provides access to Steam Market API functionality including item pricing,
 * search capabilities, app information, and market data retrieval.
 * Handles API authentication and error management.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
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
     * Image helper for retrieving item images
     * @var SteamImageHelper
     */
    private SteamImageHelper $imageHelper;

    /**
     * Optional logger instance for API call logging
     * @var LoggerService|null
     */
    private ?LoggerService $logger;

    /**
     * SteamMarketService constructor
     * 
     * Initializes the Steam API client with optional API key
     * and sets up logging if provided.
     *
     * @param LoggerService|null $logger Optional logger for API call tracking
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger;
        $this->imageHelper = new SteamImageHelper($logger);
        
        $apiKey = $_ENV['STEAM_API_KEY'] ?? null;
        
        if ($apiKey) {
            $this->steamApi = new SteamApi($apiKey);
        } else {
            // Without API key (limited functionality)
            $this->steamApi = new SteamApi();
        }
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
            if ($this->logger) {
                $this->logger->debug("Fetching price for item: {$itemName} (App ID: {$appId})");
            }
            
            $options = [
                'market_hash_name' => $itemName,
                'country' => 'US',
                'currency' => 1
            ];
            
            $response = $this->steamApi->detailed()->getItemPricing($appId, $options);
            
            if ($response && isset($response['response'])) {
                $data = $response['response'];
                      // Since getItemPricing doesn't provide image URL, we try a search
            $imageUrl = $this->imageHelper->getItemImageFromMarket($itemName, $appId);
                
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
            
            if ($this->logger) {
                $this->logger->debug("Successfully fetched price for: {$itemName}", [
                    'result' => $result
                ]);
            }
            return $result;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error fetching item price: " . $e->getMessage());
            }
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
            if ($this->logger) {
                $this->logger->info("Searching for items: {$query} (App: {$appId}, Count: {$count})");
            }

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
                    $imageUrl = $this->imageHelper->extractImageFromItem($item);

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

            if ($this->logger) {
                $this->logger->debug("Found " . count($items) . " items for query: {$query}");
            }

            return [
                'items' => $items,
                'query' => $query,
                'app_id' => $appId,
                'count' => count($items),
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error searching items: " . $e->getMessage());
            }
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
     * Get popular Steam Market items for an application.
     *
     * @param int $appId Steam application ID (default: 730 for CS2)
     * @return array Popular items array with metadata and success status
     */
    public function getPopularItems(int $appId = 730): array
    {
        try {
            $this->logger?->info("Fetching popular items for app: {$appId}");
            $options = [
                'query' => '',
                'start' => 0,
                'count' => 20,
                'search_descriptions' => false
            ];
            $response = $this->steamApi->detailed()->searchItems($appId, $options);
            $results = $response['response']['results'] ?? [];
            $popularItems = array_map(fn($item) => [
                'name' => $item['name'] ?? 'Unknown',
                'hash_name' => $item['hash_name'] ?? '',
                'sell_price' => $item['sell_price'] ?? null,
                'sell_price_text' => $item['sell_price_text'] ?? '',
                'image_url' => $this->imageHelper->extractImageFromItem($item),
                'app_id' => $appId
            ], $results);
            $this->logger?->debug("Found " . count($popularItems) . " popular items");
            return [
                'items' => $popularItems,
                'app_id' => $appId,
                'count' => count($popularItems),
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logger?->error("Error fetching popular items: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'app_id' => $appId,
                'success' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }    
}
