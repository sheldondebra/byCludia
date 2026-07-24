<?php
declare(strict_types=1);

/**
 * Front controller for PHP's built-in server.
 * Usage: php -S localhost:8080 router.php
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uriPath = rawurldecode($uriPath);
$file = __DIR__ . $uriPath;

// Serve real files (assets, admin PHP, api, sitemap, robots, etc.)
if ($uriPath !== '/' && is_file($file)) {
    return false;
}

// Directory indexes (e.g. /admin/)
if ($uriPath !== '/' && is_dir($file)) {
    $index = rtrim($file, '/\\') . '/index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

require_once __DIR__ . '/includes/bootstrap.php';

$path = trim($uriPath, '/');
if ($path === 'sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === 'robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}

$resolved = store_resolve_path($uriPath);
if ($resolved === null) {
    http_response_code(404);
    $_GET['page'] = '404';
} else {
    foreach ($resolved as $key => $value) {
        $_GET[$key] = $value;
    }
}

require __DIR__ . '/index.php';
return true;
