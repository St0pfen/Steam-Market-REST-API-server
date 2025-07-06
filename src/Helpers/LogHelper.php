<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Log Helper Class
 *
 * Provides centralized logging functionality for the application,
 * allowing for consistent logging across different components.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 * @version 1.0.0
 */
class LogHelper
{
    /**
     * Log a message with optional context
     * @param string $level The log level (e.g., 'info', 'error', 'debug')
     * @param string $message The log message
     * @param array $context Optional context data to include in the log
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Ensure the logger is available
        if (!isset($_ENV['LOGGER'])) {
            throw new \RuntimeException('Logger is not configured');
        }

        // Log the message with context
        $logger = $_ENV['LOGGER'];
        $logger->log($level, $message, $context);
    }
}