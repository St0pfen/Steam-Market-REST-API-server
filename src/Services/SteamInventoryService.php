<?php
declare(strict_types=1);

namespace App\Services;
use App\Services\LoggerService;
use Exception;
use App\Helpers\ConfigHelper;
use App\Helpers\SteamWebApiHelper;

/**
 * Steam Inventory Service
 *
 * Handles Steam inventory-related operations including fetching inventory,
 * calculating total value, and generating trade links.
 *
 * @package stopfen/steam-rest-api-php
 * @author @St0pfen
 * @version 1.0.0
 */
class SteamInventoryService
{
    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    private SteamWebApiHelper $webApi;
    private string $steamApiUrl;
    private string $steamCommunityUrl;

    /**
     * @param LoggerService $logger Logger service for inventory logging
     */
    public function __construct(LoggerService $logger, SteamWebApiHelper $webApi)
    {
        $this->logger = $logger;
        $this->webApi = $webApi;
        $this->steamApiUrl = ConfigHelper::steam('api_url');
        $this->steamCommunityUrl = ConfigHelper::steam('community_url');
    }

        /**
     * Get user's Steam inventory for a specific app
     * 
     * @param string $steamId Steam64 ID
     * @param int $appId Steam App ID (default: 730 for CS2)
     * @param int $contextId Context ID (default: 2 for items)
     * @return array|null Inventory data or null if not accessible
     */
    public function getInventory(string $steamId, int $appId, int $contextId = 2): ?array
    {
        try {
            $url = $this->steamCommunityUrl . "/inventory/{$steamId}/{$appId}/{$contextId}";
            $params = [
                'l' => 'english',
                'count' => '75'  // Steam's default limit
            ];
            
            $response = $this->webApi->makeApiCall($url, $params, true); // Changed to true for JSON response
            
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
            
            // Debug: Highlight possible knife items in descriptions
            if ($this->logger) {
                foreach ($descriptions as $desc) {
                    $name = $desc['name'] ?? '';
                    $type = $desc['type'] ?? '';
                    if (stripos($name, 'knife') !== false || stripos($name, 'dagger') !== false || strpos($name, '★') !== false || stripos($name, 'stattrak') !== false || stripos($type, 'knife') !== false || stripos($type, 'dagger') !== false) {
                        $this->logger->info('Potential knife/dagger item found in descriptions', [
                            'classid' => $desc['classid'] ?? null,
                            'instanceid' => $desc['instanceid'] ?? null,
                            'name' => $name,
                            'type' => $type,
                            'market_name' => $desc['market_name'] ?? null,
                            'market_hash_name' => $desc['market_hash_name'] ?? null,
                            'tags' => $desc['tags'] ?? null
                        ]);
                    }
                }
            }
            
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