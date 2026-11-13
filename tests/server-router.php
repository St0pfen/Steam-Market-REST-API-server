<?php
/**
 * Router für den PHP-Entwicklungsserver
 *
 * Emuliert die Apache-Rewrite-Regel: existierende Dateien werden direkt
 * ausgeliefert, alles andere geht an public/index.php (Slim-Front-Controller).
 *
 * @package stopfen/steam-rest-api-php
 */

declare(strict_types=1);

$publicDir = __DIR__ . '/../public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath($publicDir . $path);

if ($file !== false && str_starts_with($file, realpath($publicDir)) && is_file($file)) {
    return false; // statische Datei vom eingebauten Server ausliefern
}

require $publicDir . '/index.php';