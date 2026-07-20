<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
db();

header('Content-Type: application/xml; charset=UTF-8');

/** @var array<int,array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> $urls */
$urls = [];

$add = static function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.6') use (&$urls): void {
    $urls[] = ['loc' => $loc, 'lastmod' => $lastmod, 'changefreq' => $changefreq, 'priority' => $priority];
};

// Static pages
$add(url(), null, 'daily', '1.0');
$add(url('shop'), null, 'daily', '0.9');
$add(url('blog'), null, 'weekly', '0.7');
$add(url('about'), null, 'monthly', '0.5');
$add(url('faq'), null, 'monthly', '0.5');
$add(url('contact'), null, 'monthly', '0.5');

// Products
try {
    $products = db()->query('SELECT slug, created_at FROM products WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
    foreach ($products as $p) {
        $lastmod = !empty($p['created_at']) ? date('Y-m-d', (int) strtotime((string) $p['created_at'])) : null;
        $add(url('product/' . $p['slug']), $lastmod, 'weekly', '0.8');
    }
} catch (Throwable $e) {
}

// Blog posts
try {
    $posts = db()->query('SELECT slug, published_at FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC')->fetchAll();
    foreach ($posts as $post) {
        $lastmod = !empty($post['published_at']) ? date('Y-m-d', (int) strtotime((string) $post['published_at'])) : null;
        $add(url('blog/' . $post['slug']), $lastmod, 'monthly', '0.6');
    }
} catch (Throwable $e) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
