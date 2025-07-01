<?php
declare(strict_types=1);

namespace App\Helpers;

class ConfigHelper
{
    /**
     * Get environment variable with fallback
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
     * Get application configuration
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
     * Get CORS configuration
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
     * Get Steam API configuration
     */
    public static function steam(string $key = null)
    {
        $config = [
            'api_key' => self::env('STEAM_API_KEY', null),
        ];
        
        return $key ? ($config[$key] ?? null) : $config;
    }
}