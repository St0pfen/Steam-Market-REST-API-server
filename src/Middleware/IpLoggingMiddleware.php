<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * IP Logging Middleware
 * 
 * PSR-15 middleware that logs IP addresses, request details, and response metrics
 * for all incoming HTTP requests. Provides detailed analytics and monitoring
 * capabilities for API usage tracking.
 *
 * @package stopfen/steam-rest-api-php
 * @author @StopfMich
 * @version 1.0.0
 */
class IpLoggingMiddleware
{
    /**
     * Logger service instance for request logging
     * @var LoggerService
     */
    private LoggerService $logger;
    
    /**
     * IpLoggingMiddleware constructor
     * 
     * Initializes the middleware with a new LoggerService instance
     * for handling request and response logging.
     */
    public function __construct()
    {
        $this->logger = new LoggerService();
    }
    
    /**
     * Process an incoming request and log details
     * 
     * Handles the HTTP request, measures response time, extracts client information,
     * and logs comprehensive request/response metrics for monitoring and analytics.
     *
     * @param Request $request The incoming HTTP request
     * @param RequestHandler $handler The request handler to process the request
     * @return Response The HTTP response after processing and logging
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $startTime = microtime(true);
        
        // Request-Informationen sammeln
        $serverParams = $request->getServerParams();
        $ip = LoggerService::getRealIpAddress($serverParams);
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $userAgent = $request->getHeaderLine('User-Agent');
        
        // Request verarbeiten
        $response = $handler->handle($request);
        
        // Response-Zeit berechnen
        $responseTime = microtime(true) - $startTime;
        $responseCode = $response->getStatusCode();
        
        // IP-Zugriff loggen
        $this->logger->logIpAccess(
            $ip,
            $method,
            $uri,
            $userAgent,
            $responseCode,
            $responseTime
        );
        
        // Additional detailed logs for API endpoints
        if (str_starts_with($uri, '/api/')) {
            $this->logApiDetails($request, $response, $ip, $responseTime);
        }
        
        // Response zurückgeben
        return $response;
    }
    
    /**
     * Log detailed API-specific information
     * 
     * Records comprehensive API call details including parameters, timing,
     * and special handling for Steam API endpoints with performance monitoring.
     *
     * @param Request $request The HTTP request object
     * @param Response $response The HTTP response object
     * @param string $ip Client IP address
     * @param float $responseTime Response time in seconds
     * @return void
     */
    private function logApiDetails(Request $request, Response $response, string $ip, float $responseTime): void
    {
        $uri = (string) $request->getUri();
        $queryParams = $request->getQueryParams();
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        
        // Sammle zusätzliche Details
        $details = [
            'ip' => $ip,
            'method' => $method,
            'endpoint' => $uri,
            'query_params' => $queryParams,
            'status_code' => $statusCode,
            'response_time_ms' => round($responseTime * 1000, 2),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'referer' => $request->getHeaderLine('Referer') ?: 'direct'
        ];
        
        // Special attention for Steam API calls
        if (str_contains($uri, '/steam/')) {
            $this->logger->logSteamApiEvent('API Call', $details);
        } else {
            $this->logger->logDetailedRequest($details);
        }
        
        // Warnings for unusual activities
        if ($responseTime > 5.0) {
            $this->logger->warning('Slow API Response', [
                'ip' => $ip,
                'endpoint' => $uri,
                'response_time' => $responseTime
            ]);
        }
        
        if ($statusCode >= 400) {
            $this->logger->warning('HTTP Error Response', [
                'ip' => $ip,
                'endpoint' => $uri,
                'status_code' => $statusCode
            ]);
        }
    }
}
