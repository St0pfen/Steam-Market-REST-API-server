<?php
declare(strict_types=1);

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class LoggerService
{
    private Logger $logger;
    private Logger $ipLogger;
    private string $logPath;
    
    public function __construct()
    {
        $this->logPath = __DIR__ . '/../../logs';
        
        // Logs-Verzeichnis erstellen falls es nicht existiert
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
        
        $this->initializeLoggers();
    }
    
    private function initializeLoggers(): void
    {
        // Main logger for general logs
        $this->logger = new Logger('steam_api');
        
        $generalHandler = new RotatingFileHandler(
            $this->logPath . '/app.log',
            30, // 30 Tage behalten
            Logger::INFO
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
            30, // 30 Tage behalten
            Logger::INFO
        );
        
        $ipFormatter = new LineFormatter(
            "[%datetime%] %message%\n",
            'Y-m-d H:i:s'
        );
        $ipHandler->setFormatter($ipFormatter);
        $this->ipLogger->pushHandler($ipHandler);
    }
    
    /**
     * Loggt IP-Adresse und Request-Details
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
     * Loggt allgemeine Anwendungs-Events
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
    
    /**
     * Helper methods for different log levels
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    
    /**
     * Extrahiert die echte IP-Adresse (berücksichtigt Proxies)
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
     * Logs detailed request information for debugging
     */
    public function logDetailedRequest(array $requestData): void
    {
        $this->info('Detailed Request Log', $requestData);
    }
    
    /**
     * Logs Steam API spezifische events
     */
    public function logSteamApiEvent(string $event, array $data = []): void
    {
        $this->info("Steam API: {$event}", $data);
    }
}
