<?php
declare(strict_types=1);

/**
 * Pretty storefront URL routing.
 * Converts legacy index.php?page=… links into clean paths and resolves incoming requests.
 */

/** @return list<string> */
function store_allowed_pages(): array
{
    return [
        'home', 'shop', 'product', 'cart', 'checkout', 'about', 'faq', 'contact',
        'login', 'register', 'logout', 'account', 'order-success', 'wishlist', 'wishlist-shared',
        'blog', 'blog-post', 'compare', 'checkout-return', 'gift-cards', 'track', 'unsubscribe',
        'returns-policy', 'privacy-policy', 'shipping-policy', 'terms', '404',
    ];
}

/** Static path segment => page name (excluding dynamic product/blog/share routes). */
function store_static_paths(): array
{
    return [
        '' => 'home',
        'home' => 'home',
        'shop' => 'shop',
        'cart' => 'cart',
        'checkout' => 'checkout',
        'about' => 'about',
        'faq' => 'faq',
        'contact' => 'contact',
        'login' => 'login',
        'register' => 'register',
        'logout' => 'logout',
        'account' => 'account',
        'order-success' => 'order-success',
        'wishlist' => 'wishlist',
        'blog' => 'blog',
        'compare' => 'compare',
        'checkout-return' => 'checkout-return',
        'gift-cards' => 'gift-cards',
        'track' => 'track',
        'unsubscribe' => 'unsubscribe',
        'returns' => 'returns-policy',
        'returns-policy' => 'returns-policy',
        'privacy' => 'privacy-policy',
        'privacy-policy' => 'privacy-policy',
        'shipping' => 'shipping-policy',
        'shipping-policy' => 'shipping-policy',
        'terms' => 'terms',
    ];
}

/**
 * Build a clean path (no leading slash) from a page + query params.
 *
 * @param array<string, scalar|null> $params
 */
function store_build_path(string $page, array $params = []): string
{
    $page = preg_replace('/[^a-z0-9\-]/', '', strtolower($page)) ?: 'home';
    $slug = isset($params['slug']) ? trim((string) $params['slug']) : '';
    $token = isset($params['token']) ? trim((string) $params['token']) : '';
    unset($params['slug'], $params['token'], $params['page']);

    $path = match ($page) {
        'home' => '',
        'product' => 'product/' . rawurlencode($slug),
        'blog-post' => 'blog/' . rawurlencode($slug),
        'wishlist-shared' => 'wishlist/share/' . rawurlencode($token),
        'returns-policy' => 'returns',
        'privacy-policy' => 'privacy',
        'shipping-policy' => 'shipping',
        '404' => '404',
        default => $page,
    };

    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[(string) $key] = $value;
    }
    if ($query === []) {
        return $path;
    }
    return $path . '?' . http_build_query($query);
}

/**
 * Normalize any storefront path / legacy index.php?page=… into a clean relative path.
 */
function store_pretty_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');
    $query = null;

    if (preg_match('#^index\.php(?:\?(.*))?$#i', $path, $m)) {
        parse_str($m[1] ?? '', $query);
    } elseif (str_starts_with($path, '?') && str_contains($path, 'page=')) {
        parse_str(substr($path, 1), $query);
    } else {
        return $path;
    }

    $page = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($query['page'] ?? 'home'))) ?: 'home';
    unset($query['page']);
    return store_build_path($page, $query);
}

/**
 * Resolve an incoming request path into page + params for $_GET.
 *
 * @return array{page:string,slug?:string,token?:string}|null null = not a store route (404)
 */
function store_resolve_path(string $uriPath): ?array
{
    $path = trim(rawurldecode($uriPath), '/');
    if (str_ends_with(strtolower($path), '.php')) {
        return null;
    }

    if (preg_match('#^product/([a-z0-9\-]+)$#i', $path, $m)) {
        return ['page' => 'product', 'slug' => strtolower($m[1])];
    }
    if (preg_match('#^blog/([a-z0-9\-]+)$#i', $path, $m)) {
        return ['page' => 'blog-post', 'slug' => strtolower($m[1])];
    }
    if (preg_match('#^wishlist/share/([a-f0-9]{32})$#i', $path, $m)) {
        return ['page' => 'wishlist-shared', 'token' => strtolower($m[1])];
    }

    $static = store_static_paths();
    if (array_key_exists($path, $static)) {
        return ['page' => $static[$path]];
    }

    return null;
}
