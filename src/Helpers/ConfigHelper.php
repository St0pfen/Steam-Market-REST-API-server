<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Configuration Helper Class
 * 
 * Provides centralized access to application configuration settings,
 * environment variables, and configuration management.
 *
 * @package stopfen/steam-rest-api-php
 * @author @StopfMich
 * @version 1.0.0
 */
class ConfigHelper
{
    /**
     * Get environment variable with fallback value
     * 
     * Retrieves environment variables with proper type conversion for boolean values.
     * Falls back to default value if the environment variable is not set.
     *
     * @param string $key The environment variable key to retrieve
     * @param mixed $default Default value to return if the environment variable is not set
     * @return mixed The environment variable value or default value
     */
    public static function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key) ?? $default;
        
        // Handle boolean strings
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
        }
        
        return $value;
    }
    
    /**
     * Get application configuration settings
     * 
     * Retrieves application-specific configuration values including
     * application name, version, debug mode, URLs, and content types.
     *
     * @param string|null $key Specific configuration key to retrieve, or null for all settings
     * @return mixed Single configuration value if key specified, or array of all settings
     */
    public static function app(string $key = null)
    {
        $config = [
            'name' => self::env('APP_NAME', 'Steam REST API'),
            'version' => self::env('APP_VERSION', '1.0.0'),
            'debug' => self::env('APP_DEBUG', false),
            'base_url' => self::env('APP_BASE_URL', 'http://localhost:8000'),
            'api_prefix' => self::env('APP_API_PREFIX', 'api/v1'),
            'content_type' => self::env('APP_CONTENT_TYPE', 'application/json'),
        ];
        
        return $key ? ($config[$key] ?? null) : $config;
    }
    
    /**
     * Get CORS (Cross-Origin Resource Sharing) configuration
     * 
     * Retrieves CORS settings for handling cross-origin requests
     * including allowed origins, headers, and HTTP methods.
     *
     * @param string|null $key Specific CORS configuration key to retrieve, or null for all settings
     * @return mixed Single CORS configuration value if key specified, or array of all CORS settings
     */
    public static function cors(string $key = null)
    {
        $config = [
            'allow_origin' => self::env('CORS_ALLOW_ORIGIN', '*'),
            'allow_headers' => self::env('CORS_ALLOW_HEADERS', 'X-Requested-With, Content-Type, Accept, Origin, Authorization'),
            'allow_methods' => self::env('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, PATCH, OPTIONS'),
        ];
        
        return $key ? ($config[$key] ?? null) : $config;
    }
    
    /**
     * Get Steam API configuration settings
     * 
     * Retrieves Steam API specific configuration including the API key
     * required for accessing Steam Web API services.
     *
     * @param string|null $key Specific Steam configuration key to retrieve, or null for all settings
     * @return mixed Single Steam configuration value if key specified, or array of all Steam settings
     */
    public static function steam(string $key = null)
    {
        $config = [
            'api_key' => self::env('STEAM_API_KEY', null),
        ];
        
        return $key ? ($config[$key] ?? null) : $config;
    }
}