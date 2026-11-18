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
     * Fallback user agent used when STEAM_HTTP_USER_AGENT is not configured.
     */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    /**
     * Fallback timeout (seconds) used when STEAM_HTTP_TIMEOUT is not configured.
     */
    public const DEFAULT_TIMEOUT = 15;

    /**
     * Optional logger instance for debugging and monitoring
     * @var LoggerService|null
     */
    private ?LoggerService $logger;

    /**
     * Timeout in seconds for outgoing HTTP requests
     * @var int
     */
    private int $timeout;

    /**
     * User agent sent with outgoing HTTP requests
     * @var string
     */
    private string $userAgent;

    /**
     * SteamWebApiHelper constructor
     *
     * Timeout and user agent default to the STEAM_HTTP_TIMEOUT /
     * STEAM_HTTP_USER_AGENT environment variables, so callers can tune
     * upstream behaviour without touching code.
     *
     * @param LoggerService|null $logger Optional logger for debugging
     * @param int|null $timeout Request timeout in seconds (null = from config)
     * @param string|null $userAgent User agent (null = from config)
     */
    public function __construct(?LoggerService $logger = null, ?int $timeout = null, ?string $userAgent = null)
    {
        $this->logger = $logger;

        if ($timeout === null) {
            $configured = ConfigHelper::env('STEAM_HTTP_TIMEOUT', self::DEFAULT_TIMEOUT);
            $timeout = is_numeric($configured) && (int) $configured > 0
                ? (int) $configured
                : self::DEFAULT_TIMEOUT;
        }
        $this->timeout = $timeout;

        if ($userAgent === null) {
            $configured = ConfigHelper::env('STEAM_HTTP_USER_AGENT', self::DEFAULT_USER_AGENT);
            $userAgent = is_string($configured) && trim($configured) !== ''
                ? trim($configured)
                : self::DEFAULT_USER_AGENT;
        }
        $this->userAgent = $userAgent;
    }

    /**
     * Build the full request URL, appending query parameters only when present.
     *
     * Previous behaviour always appended a "?" — even for parameterless
     * requests, which produced URLs like "https://host/path?" and broke
     * strict upstream signature/cache comparisons.
     *
     * @param string $url Base URL
     * @param array<string, mixed> $params Query parameters
     * @return string Complete URL
     */
    public function buildUrl(string $url, array $params = []): string
    {
        if ($params === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    /**
     * Configured request timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Configured user agent string.
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
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
            $fullUrl = $this->buildUrl($url, $params);

            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->timeout,
                    'user_agent' => $this->userAgent,
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