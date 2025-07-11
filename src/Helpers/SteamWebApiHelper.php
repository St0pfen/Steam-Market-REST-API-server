<?php
declare(strict_types=1);
namespace App\Helpers;

use App\Helpers\ConfigHelper;
use App\Services\LoggerService;
use Exception;
/**
 * SteamWebApiHelper Class
 *
 * Provides utility methods for interacting with the Steam Web API,
 * including making API calls and handling responses.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class SteamWebApiHelper
{
    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerService|null
     */
    private ?LoggerService $logger;

    /**
     * SteamWebApiHelper constructor
     *
     * @param LoggerService|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerService $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Make API call to Steam endpoints
     * 
     * @param string $url URL to call
     * @param array $params Query parameters
     * @param bool $isJsonResponse Whether to expect JSON response (default: true)
     * @return array|null Response data or null on failure
     */
    public function makeApiCall(string $url, array $params = [], bool $isJsonResponse = true): ?array
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
}