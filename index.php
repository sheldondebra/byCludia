<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Ensure DB is ready (creates SQLite + seed if needed)
db();

$page = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['page'] ?? 'home'))) ?: 'home';

$allowed = [
    'home', 'shop', 'product', 'cart', 'checkout', 'about', 'faq', 'contact',
    'login', 'register', 'logout', 'account', 'order-success', 'wishlist', 'blog', 'blog-post', 'compare', 'checkout-return',
    'gift-cards',
    'returns-policy', 'privacy-policy', 'shipping-policy', 'terms',
];

if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = '404';
}

if ($page === 'logout') {
    logout_user();
    flash('success', 'You have been signed out.');
    redirect('index.php?page=home');
}

$file = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($file)) {
    http_response_code(404);
    $file = __DIR__ . '/pages/404.php';
}

require $file;
