<?php
/**
 * PHPUnit Bootstrap
 *
 * Startet für die Integrationstests einen PHP-Entwicklungsserver gegen
 * public/index.php, sofern nicht TEST_BASE_URL gesetzt ist (dann läuft
 * die Suite gegen einen bereits laufenden Dienst, z. B. den Docker-Container).
 *
 * @package stopfen/steam-rest-api-php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// .env für den Server-Prozess bereitstellen (kopiert .env.example, falls nötig)
$root = dirname(__DIR__);
if (!is_file($root . '/.env') && is_file($root . '/.env.example')) {
    copy($root . '/.env.example', $root . '/.env');
}

$external = getenv('TEST_BASE_URL') ?: '';
$baseUrl = $external !== '' ? $external : 'http://127.0.0.1:8000';
putenv('TEST_BASE_URL=' . $baseUrl);
$_ENV['TEST_BASE_URL'] = $baseUrl;

if ($external !== '') {
    fwrite(STDERR, "[bootstrap] Externer Server: {$baseUrl}\n");
    return;
}

/**
 * Wartet, bis der Server Verbindungen annimmt.
 */
function waitForServer(string $host, int $port, float $timeout = 10.0): bool
{
    $deadline = microtime(true) + $timeout;
    while (microtime(true) < $deadline) {
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if ($socket !== false) {
            fclose($socket);
            return true;
        }
        usleep(100_000);
    }
    return false;
}

$host = '127.0.0.1';
$port = 8000;

[$host, $port] = (static function (string $url): array {
    $parts = parse_url($url);
    return [$parts['host'] ?? '127.0.0.1', (int)($parts['port'] ?? 80)];
})($baseUrl);

$command = [PHP_BINARY, '-S', $host . ':' . $port, '-t', $root . '/public', $root . '/tests/server-router.php'];
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', '/dev/null', 'w'],
    2 => ['file', '/dev/null', 'w'],
];
$process = proc_open($command, $descriptors, $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, "[bootstrap] Server konnte nicht gestartet werden.\n");
    exit(1);
}

register_shutdown_function(static function () use ($process, $pipes): void {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($process);
    proc_close($process);
});

if (!waitForServer($host, $port)) {
    fwrite(STDERR, "[bootstrap] Server auf {$host}:{$port} nicht erreichbar.\n");
    exit(1);
}

fwrite(STDERR, "[bootstrap] Entwicklungsserver läuft auf {$baseUrl}\n");