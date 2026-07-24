<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
db();

header('Content-Type: application/xml; charset=UTF-8');

/** @var list<array{loc:string,lastmod?:?string,changefreq:string,priority:string,image?:?string}> $urls */
$urls = [];

$add = static function (
    string $loc,
    ?string $lastmod = null,
    string $changefreq = 'weekly',
    string $priority = '0.6',
    ?string $image = null
) use (&$urls): void {
    $urls[] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
        'image' => $image,
    ];
};

$ymd = static function (?string $raw): ?string {
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
};

// Static pages
$add(url(), null, 'daily', '1.0');
$add(url('shop'), null, 'daily', '0.9');
$add(url('blog'), null, 'weekly', '0.7');
$add(url('about'), null, 'monthly', '0.5');
$add(url('faq'), null, 'monthly', '0.5');
$add(url('contact'), null, 'monthly', '0.5');
$add(url('gift-cards'), null, 'monthly', '0.5');
$add(url('privacy'), null, 'yearly', '0.3');
$add(url('returns'), null, 'yearly', '0.3');
$add(url('shipping'), null, 'yearly', '0.3');
$add(url('terms'), null, 'yearly', '0.3');

// Categories
try {
    $categories = db()->query('SELECT slug FROM categories ORDER BY sort_order, name')->fetchAll();
    foreach ($categories as $cat) {
        $add(url('shop?category=' . rawurlencode((string) $cat['slug'])), null, 'weekly', '0.7');
    }
} catch (Throwable $e) {
}

// Products
try {
    $products = db()->query(
        'SELECT slug, image, created_at, updated_at FROM products WHERE is_active = 1 ORDER BY id DESC'
    )->fetchAll();
    foreach ($products as $p) {
        $lastmod = $ymd((string) ($p['updated_at'] ?? '')) ?: $ymd((string) ($p['created_at'] ?? ''));
        $image = !empty($p['image']) ? asset((string) $p['image']) : null;
        $add(url('product/' . $p['slug']), $lastmod, 'weekly', '0.8', $image);
    }
} catch (Throwable $e) {
}

// Blog posts
try {
    $posts = db()->query(
        'SELECT slug, image, published_at FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC'
    )->fetchAll();
    foreach ($posts as $post) {
        $lastmod = $ymd((string) ($post['published_at'] ?? ''));
        $image = !empty($post['image']) ? asset((string) $post['image']) : null;
        $add(url('blog/' . $post['slug']), $lastmod, 'monthly', '0.6', $image);
    }
} catch (Throwable $e) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    if (!empty($u['image'])) {
        echo '    <image:image><image:loc>' . htmlspecialchars((string) $u['image'], ENT_XML1) . '</image:loc></image:image>' . "\n";
    }
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
