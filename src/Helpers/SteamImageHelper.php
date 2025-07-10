<?php

declare(strict_types=1);

namespace App\Helpers;


use SteamApi\SteamApi;
use Psr\Log\LoggerInterface;

/**
 * SteamImageHelper Class
 * 
 * Provides utility methods for ripping Steam image from Marketplace items.
 * 
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamImageHelper
{
    /**
     * Steam Web API helper instance
     * 
     * @var SteamWebApiHelper
     */
    private SteamApi $steamApi;

    /**
     * Optional logger instance for debugging and monitoring
     * 
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * Constructor for SteamImageHelper
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
    public function getItemImageFromMarket(string $marketHashName, int $appId): ?string
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
            $this->logger->log('warning', "Could not fetch item image from market: " . $e->getMessage());
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
    public function extractImageFromItem(array $item): ?string
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
}
