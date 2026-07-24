<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path, int $status = 302): never
{
    global $config;
    if (!str_starts_with($path, 'http')) {
        $pretty = store_pretty_path($path);
        if (preg_match('#^https?://#i', $pretty)) {
            $path = $pretty;
        } else {
            $base = rtrim((string) $config['app_url'], '/');
            $path = $pretty === '' ? $base . '/' : $base . '/' . ltrim($pretty, '/');
        }
    }
    header('Location: ' . $path, true, $status);
    exit;
}

function url(string $path = ''): string
{
    global $config;
    $base = rtrim((string) $config['app_url'], '/');
    if ($path === '' || $path === '/') {
        return $base . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $pretty = store_pretty_path($path);
    if (preg_match('#^https?://#i', $pretty)) {
        return $pretty;
    }
    if ($pretty === '' || $pretty === '/') {
        return $base . '/';
    }
    return $base . '/' . ltrim($pretty, '/');
}

function asset(string $path): string
{
    return url(ltrim($path, '/'));
}

/** Digits-only phone for wa.me links (keeps country code, strips + and spaces). */
function whatsapp_number(?string $phone = null): string
{
    $phone = $phone ?? (string) setting('contact_phone', '');
    return preg_replace('/\D+/', '', $phone) ?? '';
}

/**
 * Build a WhatsApp click-to-chat URL with a prefilled order message.
 *
 * @param array{name?:string,variant?:string,quantity?:int|string,price?:string,url?:string} $order
 */
function whatsapp_order_url(array $order = [], ?string $phone = null): string
{
    $wa = whatsapp_number($phone);
    if ($wa === '') {
        return '#';
    }
    $store = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
    $lines = [
        'Hi ' . $store . '! I would like to order:',
        '',
        '• Product: ' . trim((string) ($order['name'] ?? 'Product')),
    ];
    $variant = trim((string) ($order['variant'] ?? ''));
    if ($variant !== '') {
        $lines[] = '• Option: ' . $variant;
    }
    $qty = (int) ($order['quantity'] ?? 1);
    $lines[] = '• Quantity: ' . max(1, $qty);
    $price = trim((string) ($order['price'] ?? ''));
    if ($price !== '') {
        $lines[] = '• Price: ' . $price;
    }
    $productUrl = trim((string) ($order['url'] ?? ''));
    if ($productUrl !== '') {
        $lines[] = '';
        $lines[] = 'Link: ' . $productUrl;
    }
    $lines[] = '';
    $lines[] = 'Please confirm availability and how to pay. Thank you!';
    return 'https://wa.me/' . $wa . '?text=' . rawurlencode(implode("\n", $lines));
}

/**
 * WhatsApp link to ask a product question (prefilled; user types the question).
 *
 * @param array{name?:string,url?:string} $ctx
 */
function whatsapp_question_url(array $ctx = [], ?string $phone = null): string
{
    $wa = whatsapp_number($phone);
    if ($wa === '') {
        return '#';
    }
    $store = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
    $lines = [
        'Hi ' . $store . '!',
        '',
        'I have a question about this product:',
        '• ' . trim((string) ($ctx['name'] ?? 'Product')),
    ];
    $productUrl = trim((string) ($ctx['url'] ?? ''));
    if ($productUrl !== '') {
        $lines[] = '• Link: ' . $productUrl;
    }
    $lines[] = '';
    $lines[] = 'My question:';
    return 'https://wa.me/' . $wa . '?text=' . rawurlencode(implode("\n", $lines));
}

/** Absolute URL of the page currently being requested (for canonical/og:url fallback). */
function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

/** Render a JSON-LD structured-data script tag from a PHP array. */
function json_ld(array $data): string
{
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . '</script>';
}

function settings_all(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $cache = settings_all();
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')
            ->execute([$key, $value]);
    } else {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
            ->execute([$key, $value]);
    }
    settings_all(true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function stars(float $rating): string
{
    $full = (int) floor($rating);
    $html = '';
    for ($i = 0; $i < 5; $i++) {
        $html .= $i < $full
            ? '<span class="text-amber-500">★</span>'
            : '<span class="text-stone-300">★</span>';
    }
    return $html;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function order_number(): string
{
    return 'CD-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymd');
}

function active_nav(string $page): string
{
    $current = $_GET['page'] ?? 'home';
    return $current === $page ? 'text-brand-ink font-semibold' : 'text-brand-ink/70 hover:text-brand-ink';
}

/**
 * Colored status pill for admin order statuses.
 */
function admin_status_badge(string $status): string
{
    $map = [
        'pending'    => 'bg-amber-100 text-amber-800',
        'paid'       => 'bg-emerald-100 text-emerald-800',
        'processing' => 'bg-sky-100 text-sky-800',
        'shipped'    => 'bg-indigo-100 text-indigo-800',
        'delivered'  => 'bg-green-100 text-green-800',
        'cancelled'  => 'bg-rose-100 text-rose-700',
        'refunded'   => 'bg-stone-200 text-stone-700',
    ];
    $classes = $map[$status] ?? 'bg-stone-100 text-stone-600';
    return '<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium capitalize ' . $classes . '">'
        . e($status) . '</span>';
}

/**
 * Active-nav classes for the admin sidebar based on the current script filename.
 */
function admin_active_nav(string $file): string
{
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isActive = $current === $file;
    // Group Email Marketing pages under the email.php nav item
    if ($file === 'email.php' && str_starts_with($current, 'email')) {
        $isActive = true;
    }
    $active = 'flex items-center gap-3 rounded-lg px-3 py-2 bg-[#F3C4C4] text-stone-900 font-semibold';
    $idle   = 'flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5';
    return $isActive ? $active : $idle;
}

/**
 * Emit a Lucide icon element. Rendered client-side by lucide.createIcons().
 */
function admin_icon(string $name, string $classes = 'w-4 h-4'): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($classes) . '"></i>';
}

/**
 * Available shipping methods (carriers) offered at checkout.
 * Returns [id => ['label' => string, 'rate' => float, 'carrier' => string]].
 * Falls back to a single "Standard" method if no carrier is enabled.
 */
function shipping_methods(): array
{
    $methods = [];
    if (setting('ship_dhl_enabled', '0') === '1') {
        $methods['dhl'] = [
            'label' => (string) (setting('ship_dhl_label', 'DHL Express') ?: 'DHL Express'),
            'rate' => (float) (setting('ship_dhl_rate', '0') ?: 0),
            'carrier' => 'dhl',
        ];
    }
    if (setting('ship_fedex_enabled', '0') === '1') {
        $methods['fedex'] = [
            'label' => (string) (setting('ship_fedex_label', 'FedEx International') ?: 'FedEx International'),
            'rate' => (float) (setting('ship_fedex_rate', '0') ?: 0),
            'carrier' => 'fedex',
        ];
    }
    if (!$methods) {
        $methods['standard'] = [
            'label' => 'Standard shipping',
            'rate' => (float) (setting('shipping_flat', '15') ?: 15),
            'carrier' => 'standard',
        ];
    }
    return $methods;
}

/** Human label for a stored carrier code. */
function carrier_label(?string $carrier): string
{
    return match (strtolower((string) $carrier)) {
        'dhl' => 'DHL',
        'fedex' => 'FedEx',
        'standard' => 'Standard',
        default => $carrier ? ucfirst($carrier) : '—',
    };
}

/** Public tracking URL for a carrier + tracking number, or '' if not trackable. */
function tracking_url(?string $carrier, ?string $number): string
{
    $number = trim((string) $number);
    if ($number === '') {
        return '';
    }
    return match (strtolower((string) $carrier)) {
        'dhl' => 'https://www.dhl.com/en/express/tracking.html?AWB=' . urlencode($number),
        'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($number),
        default => '',
    };
}

/** Recompute a product's rating + review_count from approved reviews. */
function recompute_product_rating(int $productId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) c, AVG(rating) a FROM reviews WHERE product_id = ? AND is_approved = 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    $count = (int) ($row['c'] ?? 0);
    $avg = $count > 0 ? round((float) $row['a'], 1) : 5.0;
    db()->prepare('UPDATE products SET rating = ?, review_count = ? WHERE id = ?')
        ->execute([$avg, $count, $productId]);
}

/**
 * Ensure a user has a wishlist share token. Pass $rotate=true to invalidate the old link.
 */
function user_wishlist_share_token(int $userId, bool $rotate = false): string
{
    if ($userId <= 0) {
        return '';
    }
    if (!$rotate) {
        $stmt = db()->prepare('SELECT wishlist_share_token FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $existing = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($existing !== '') {
            return $existing;
        }
    }

    do {
        $token = bin2hex(random_bytes(16));
        $check = db()->prepare('SELECT 1 FROM users WHERE wishlist_share_token = ?');
        $check->execute([$token]);
        $taken = (bool) $check->fetchColumn();
    } while ($taken);

    db()->prepare('UPDATE users SET wishlist_share_token = ? WHERE id = ?')->execute([$token, $userId]);
    return $token;
}

function wishlist_share_url(string $token): string
{
    $token = trim($token);
    if ($token === '') {
        return url('wishlist');
    }
    return url('wishlist/share/' . rawurlencode($token));
}

