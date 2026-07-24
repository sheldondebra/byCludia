<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Ensure DB is ready (creates SQLite + seed if needed)
db();

// Legacy URLs: /index.php?page=shop → /shop
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptBase = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (
    isset($_GET['page'])
    && ($scriptBase === 'index.php' || str_ends_with($requestPath, '/index.php') || $requestPath === '/index.php')
    && (str_contains($requestPath, 'index.php') || $scriptBase === 'index.php')
) {
    // Only redirect when the browser actually requested index.php (not an internal rewrite)
    if (str_contains($requestPath, 'index.php')) {
        $legacyPage = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $_GET['page'])) ?: 'home';
        $params = $_GET;
        unset($params['page']);
        redirect(store_build_path($legacyPage, $params), 301);
    }
}

$page = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['page'] ?? ''))) ?: '';

// Path-based resolution when page is not set (Apache fallback / direct include)
if ($page === '') {
    $resolved = store_resolve_path($requestPath);
    if ($resolved !== null) {
        foreach ($resolved as $key => $value) {
            $_GET[$key] = $value;
        }
        $page = (string) ($resolved['page'] ?? 'home');
    } else {
        $page = 'home';
        if (trim($requestPath, '/') !== '' && trim($requestPath, '/') !== 'index.php') {
            $page = '404';
        }
    }
}

$allowed = store_allowed_pages();

if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = '404';
}

if ($page === 'logout') {
    logout_user();
    flash('success', 'You have been signed out.');
    redirect('home');
}

$file = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($file)) {
    http_response_code(404);
    $file = __DIR__ . '/pages/404.php';
}

require $file;
