<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', ROOT_PATH);

$configFile = ROOT_PATH . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = ROOT_PATH . '/config/config.example.php';
}
$config = require $configFile;

// Auto-detect base URL when running under PHP built-in server / cPanel
if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // When called from /api or /admin, climb to project root URL
    if (str_ends_with($scriptDir, '/api') || str_ends_with($scriptDir, '/admin')) {
        $scriptDir = dirname($scriptDir);
    }
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }
    $config['app_url'] = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptDir, '/');
}

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/routes.php';
require_once ROOT_PATH . '/includes/seo.php';
require_once ROOT_PATH . '/includes/video.php';
require_once ROOT_PATH . '/includes/currency.php';
require_once ROOT_PATH . '/includes/cart.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/wishlist.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/email_marketing.php';
require_once ROOT_PATH . '/includes/sms.php';
require_once ROOT_PATH . '/includes/payments.php';
require_once ROOT_PATH . '/includes/gift.php';
require_once ROOT_PATH . '/includes/geo.php';
require_once ROOT_PATH . '/includes/shipping.php';
require_once ROOT_PATH . '/includes/promo.php';
require_once ROOT_PATH . '/includes/tracking.php';

// Seed SMTP from config.php into settings when MySQL is available (non-destructive).
try {
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        mailer_sync_settings_from_config(false);
    }
} catch (Throwable $e) {
    // Ignore during early install / offline local
}

