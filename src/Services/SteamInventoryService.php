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
     * Fetches the user's Steam inventory for a specific app and context(s).
     *
     * @param string $steamId Steam64 ID
     * @param int $appId Steam App ID (default: 730 for CS2)
     * @param int|array $contextId Context ID(s) (default: 2 for items, can be array)
     * @return array|null Inventory data or null if not accessible
     */
    public function getInventory(string $steamId, int $appId, $contextId = 2): ?array
    {
        try {
            $contexts = (array)$contextId;
            $items = [];
            foreach ($contexts as $ctxId) {
                $assets = $descriptions = [];
                $startAssetId = null;
                do {
                    $params = $startAssetId ? ['start_assetid' => $startAssetId] : [];
                    $url = $this->steamCommunityUrl . "/inventory/{$steamId}/{$appId}/{$ctxId}";
                    $res = $this->webApi->makeApiCall($url, $params, true);
                    if (!($res['assets'] ?? null) || !($res['descriptions'] ?? null)) break;
                    $assets = array_merge($assets, $res['assets']);
                    $descriptions = array_merge($descriptions, $res['descriptions']);
                    $startAssetId = $res['last_assetid'] ?? null;
                } while ($startAssetId);
                // Build lookup for descriptions
                $descMap = [];
                foreach ($descriptions as $d) {
                    $descMap[(string)$d['classid'].'_'.(string)$d['instanceid']] = $d;
                }
                // Map assets to items
                foreach ($assets as $a) {
                    $key = (string)$a['classid'].'_'.(string)$a['instanceid'];
                    if (!isset($descMap[$key])) continue;
                    $d = $descMap[$key];
                    $items[] = [
                        'assetid' => $a['assetid'],
                        'classid' => $a['classid'],
                        'instanceid' => $a['instanceid'],
                        'amount' => $a['amount'],
                        'name' => $d['name'] ?? 'Unknown Item',
                        'market_name' => $d['market_name'] ?? null,
                        'market_hash_name' => $d['market_hash_name'] ?? null,
                        'tradable' => $d['tradable'] ?? 0,
                        'marketable' => $d['marketable'] ?? 0,
                        'commodity' => $d['commodity'] ?? 0,
                        'type' => $d['type'] ?? null,
                        'icon_url' => isset($d['icon_url']) ? 'https://community.cloudflare.steamstatic.com/economy/image/' . $d['icon_url'] : null,
                        'icon_url_large' => isset($d['icon_url_large']) ? 'https://community.cloudflare.steamstatic.com/economy/image/' . $d['icon_url_large'] : null,
                        'exterior' => $this->extractExterior($d['name'] ?? ''),
                        'rarity' => $this->extractRarity($d['tags'] ?? [])
                    ];
                }
            }
            return [
                'steamid' => $steamId,
                'appid' => $appId,
                'contextid' => $contextId,
                'total_inventory_count' => count($items),
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