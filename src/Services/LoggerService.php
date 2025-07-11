<?php
declare(strict_types=1);

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Logger Service
 * 
 * Provides centralized logging functionality for the application.
 * Manages both general application logging and specialized IP access logging
 * with automatic log rotation and formatting.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class LoggerService
{
    /**
     * Main logger instance for general application logs
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Specialized logger instance for IP access logging
     * @var Logger
     */
    private Logger $ipLogger;
    
    /**
     * Path to the logs directory
     * @var string
     */
    private string $logPath;
    
    /**
     * LoggerService constructor
     * 
     * Initializes the logging service, creates log directory if needed,
     * and sets up both general and IP access loggers with rotation.
     */
    public function __construct()
    {
        $this->logPath = __DIR__ . '/../../logs';
        
        // Logs-Verzeichnis erstellen falls es nicht existiert
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
        
        $this->initializeLoggers();
    }
    
    /**
     * Initialize logger instances and handlers
     * 
     * Sets up main application logger and IP access logger with rotating file handlers,
     * custom formatters, and appropriate retention policies.
     *
     * @return void
     */
    private function initializeLoggers(): void
    {
        // Main logger for general logs
        $this->logger = new Logger('steam_api');
        
        $generalHandler = new RotatingFileHandler(
            $this->logPath . '/app.log',
            30, // Keep 30 days
            'debug' // Use string to avoid deprecated constant
        );
        
        $generalFormatter = new LineFormatter(
            "[%datetime%] %level_name%: %message% %context%\n",
            'Y-m-d H:i:s'
        );
        $generalHandler->setFormatter($generalFormatter);
        $this->logger->pushHandler($generalHandler);
        
        // Special IP logger for access logs
        $this->ipLogger = new Logger('ip_access');
        
        $ipHandler = new RotatingFileHandler(
            $this->logPath . '/access.log',
            30, // Keep 30 days
            'debug' // Use string to avoid deprecated constant
        );
        
        $ipFormatter = new LineFormatter(
            "[%datetime%] %message%\n",
            'Y-m-d H:i:s'
        );
        $ipHandler->setFormatter($ipFormatter);
        $this->ipLogger->pushHandler($ipHandler);
    }
    
    /**
     * Log IP access and request details
     * 
     * Records IP address, HTTP method, URI, user agent, response code,
     * and response time for access monitoring and analytics.
     *
     * @param string $ip Client IP address
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Requested URI
     * @param string $userAgent Client user agent string
     * @param int $responseCode HTTP response status code
     * @param float $responseTime Response time in seconds
     * @return void
     */
    public function logIpAccess(
        string $ip, 
        string $method, 
        string $uri, 
        string $userAgent = '', 
        int $responseCode = 200,
        float $responseTime = 0.0
    ): void {
        $message = sprintf(
            'IP: %s | %s %s | UA: %s | Status: %d | Time: %.3fs',
            $ip,
            $method,
            $uri,
            $userAgent,
            $responseCode,
            $responseTime
        );
        
        $this->ipLogger->info($message);
    }
    
    /**
     * Log general application events
     * 
     * Records application events with specified log level, message, and context.
     * Provides flexible logging for application monitoring and debugging.
     *
     * @param string $level Log level (info, warning, error, debug, etc.)
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
    
    /**
     * Log info level message
     * 
     * @param string $message Information message to log
     * @param array $context Additional context data
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    
    /**
     * Log warning level message
     * 
     * @param string $message Warning message to log
     * @param array $context Additional context data
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
    
    /**
     * Log error level message
     * 
     * @param string $message Error message to log
     * @param array $context Additional context data
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    
    /**
     * Log debug level message
     * 
     * @param string $message Debug message to log
     * @param array $context Additional context data
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    
    /**
     * Extract the real IP address from server parameters
     * 
     * Checks various HTTP headers to determine the real client IP address,
     * considering proxies, load balancers, and CDN services like Cloudflare.
     *
     * @param array $serverParams Server parameters from the request
     * @return string Real client IP address or fallback to localhost
     */
    public static function getRealIpAddress(array $serverParams): string
    {
        // Check various HTTP headers for real IP
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($serverParams[$key])) {
                $ip = $serverParams[$key];
                
                // Bei X-Forwarded-For kann eine kommagetrennte Liste stehen
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validiere IP-Adresse
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                
                // If public IP validation fails, try private IPs for local development
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1'; // Fallback
    }
    
    /**
     * Log detailed request information for debugging
     * 
     * Records comprehensive request details including parameters,
     * headers, and timing information for troubleshooting.
     *
     * @param array $requestData Detailed request information array
     * @return void
     */
    public function logDetailedRequest(array $requestData): void
    {
        $this->info('Detailed Request Log', $requestData);
    }
    
    /**
     * Log Steam API specific events
     * 
     * Records events specifically related to Steam API interactions
     * with structured data for monitoring and analysis.
     *
     * @param string $event Event name or description
     * @param array $data Additional event data and context
     * @return void
     */
    public function logSteamApiEvent(string $event, array $data = []): void
    {
        $this->info("Steam API: {$event}", $data);
    }
}
