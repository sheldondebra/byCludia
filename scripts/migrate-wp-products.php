<?php
declare(strict_types=1);

/**
 * Migrate WooCommerce products from a WordPress WXR export into byCludia.
 *
 * Usage:
 *   php scripts/migrate-wp-products.php "/path/to/export.xml"
 *   php scripts/migrate-wp-products.php  # uses default Downloads path
 */

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';
db();

$xmlPath = $argv[1] ?? (getenv('HOME') . '/Downloads/hairbyclaudiadarlene.WordPress.2026-07-24 (1).xml');
if (!is_file($xmlPath)) {
    fwrite(STDERR, "Export not found: {$xmlPath}\n");
    exit(1);
}

$imgDirRel = 'assets/images/products/migrated';
$imgDir = $root . '/' . $imgDirRel;
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0775, true);
}

echo "Loading {$xmlPath}…\n";
$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    fwrite(STDERR, "Could not parse XML.\n");
    exit(1);
}

$attachments = [];
$products = [];

foreach ($xml->channel->item as $item) {
    $wp = $item->children('wp', true);
    $type = (string) $wp->post_type;
    $id = (int) $wp->post_id;
    $meta = [];
    foreach ($wp->postmeta as $pm) {
        $meta[(string) $pm->meta_key][] = (string) $pm->meta_value;
    }

    if ($type === 'attachment') {
        $url = trim((string) $wp->attachment_url);
        if ($url === '') {
            $url = trim((string) $item->guid);
        }
        $attachments[$id] = [
            'id' => $id,
            'url' => $url,
            'title' => html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'parent' => (int) $wp->post_parent,
        ];
        continue;
    }

    if ($type !== 'product') {
        continue;
    }
    if ((string) $wp->status !== 'publish') {
        continue;
    }

    $cats = [];
    $taxTerms = [];
    foreach ($item->category as $c) {
        $domain = (string) $c['domain'];
        $slug = (string) $c['nicename'];
        $name = html_entity_decode((string) $c, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cats[] = ['domain' => $domain, 'slug' => $slug, 'name' => $name];
        if (str_starts_with($domain, 'pa_')) {
            $taxTerms[$domain][] = ['slug' => $slug, 'name' => $name];
        }
    }

    $products[] = [
        'id' => $id,
        'title' => html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'slug' => (string) $wp->post_name,
        'content' => (string) $item->children('content', true)->encoded,
        'excerpt' => (string) $item->children('excerpt', true)->encoded,
        'meta' => $meta,
        'cats' => $cats,
        'tax_terms' => $taxTerms,
    ];
}

echo 'Found ' . count($products) . " published products, " . count($attachments) . " attachments.\n";

/** @return list<string> */
function wp_meta_list(array $meta, string $key): array
{
    return array_values(array_filter(array_map('trim', $meta[$key] ?? []), static fn($v) => $v !== ''));
}

function wp_html_to_text(string $html): string
{
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#</(p|div|h[1-6]|li|tr|br)\s*>#i', "\n", $html) ?? $html;
    $html = preg_replace('#<(br|hr)\s*/?>#i', "\n", $html) ?? $html;
    $html = preg_replace('#<li[^>]*>#i', "• ", $html) ?? $html;
    $text = trim(strip_tags($html));
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function wp_download_image(string $url, string $destDir, string $destRel): ?string
{
    if ($url === '') {
        return null;
    }
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $base = basename($path);
    if ($base === '' || $base === '/') {
        $base = 'img-' . substr(sha1($url), 0, 12) . '.jpg';
    }
    $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base) ?: ('img-' . substr(sha1($url), 0, 12) . '.jpg');
    $localPath = $destDir . '/' . $base;
    $rel = $destRel . '/' . $base;

    // Reuse existing file under migrated or wp folders
    $candidates = [
        $localPath,
        dirname($destDir) . '/wp/' . $base,
        dirname($destDir) . '/wp/' . rawurldecode($base),
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand) && filesize($cand) > 500) {
            if ($cand !== $localPath && !is_file($localPath)) {
                @copy($cand, $localPath);
            }
            return is_file($localPath) ? $rel : (str_contains($cand, '/wp/') ? 'assets/images/products/wp/' . basename($cand) : $rel);
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 45,
            'follow_location' => 1,
            'user_agent' => 'byCludiaMigrator/1.0',
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 500) {
        // try without query / with decoded basename
        return null;
    }
    if (@file_put_contents($localPath, $data) === false) {
        return null;
    }
    return $rel;
}

/**
 * Build variation labels + prices from Woo meta.
 *
 * @return list<array{label:string,length:?string,price:float}>
 */
function wp_build_variants(array $product): array
{
    $meta = $product['meta'];
    $prices = array_map('floatval', wp_meta_list($meta, '_price'));
    if ($prices === []) {
        $prices = array_map('floatval', wp_meta_list($meta, '_regular_price'));
    }

    $attrs = [];
    $raw = wp_meta_list($meta, '_product_attributes')[0] ?? '';
    $parsed = $raw !== '' ? @unserialize($raw) : false;
    if (is_array($parsed)) {
        foreach ($parsed as $key => $attr) {
            if (!is_array($attr)) {
                continue;
            }
            if (isset($attr['is_variation']) && !(int) $attr['is_variation']) {
                continue;
            }
            $name = (string) ($attr['name'] ?? $key);
            $vals = [];
            if (!empty($attr['value'])) {
                $vals = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', (string) $attr['value']) ?: [])));
            }
            // Taxonomy attributes often have empty value; pull from item categories
            if ($vals === [] && str_starts_with((string) $key, 'pa_')) {
                foreach ($product['tax_terms'][(string) $key] ?? [] as $term) {
                    $vals[] = $term['name'];
                }
            }
            if ($name === 'pa_length' || $name === 'pa_bundles' || $name === 'pa_texture') {
                $name = match ($name) {
                    'pa_length' => 'Length',
                    'pa_bundles' => 'Bundles',
                    'pa_texture' => 'Texture',
                    default => $name,
                };
            }
            if (str_starts_with($name, 'pa_')) {
                $name = ucwords(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $name));
            }
            $attrs[] = [
                'key' => (string) $key,
                'name' => $name,
                'values' => $vals,
                'position' => (int) ($attr['position'] ?? 0),
            ];
        }
        usort($attrs, static fn($a, $b) => $a['position'] <=> $b['position']);
    }

    // Prefer attribute whose value count matches price count
    $primary = null;
    foreach ($attrs as $attr) {
        if (count($attr['values']) === count($prices) && count($prices) > 0) {
            $primary = $attr;
            break;
        }
    }
    if ($primary === null && count($attrs) === 1 && $attrs[0]['values']) {
        $primary = $attrs[0];
    }

    $variants = [];
    if ($primary && $prices) {
        $n = min(count($primary['values']), count($prices));
        for ($i = 0; $i < $n; $i++) {
            $label = $primary['values'][$i];
            $length = null;
            if (preg_match('/(\d+)\s*(?:inches|inch|\")/i', $label, $m)) {
                $length = $m[1] . '"';
            }
            $variants[] = [
                'label' => $label,
                'length' => $length,
                'price' => $prices[$i],
            ];
        }
        // leftover prices
        for ($i = $n; $i < count($prices); $i++) {
            $variants[] = [
                'label' => 'Option ' . ($i + 1),
                'length' => null,
                'price' => $prices[$i],
            ];
        }
        return $variants;
    }

    // Multi-attribute cartesian (e.g. crochet length × install), zip with prices.
    // WooCommerce _price meta in this export is ordered with the LAST attribute as the outer loop.
    if (count($attrs) >= 2 && $prices) {
        $axes = array_values(array_filter($attrs, static fn($a) => $a['values'] !== []));
        if (count($axes) >= 2) {
            $axes = array_reverse($axes);
            $combos = [[]];
            foreach ($axes as $axis) {
                $next = [];
                foreach ($combos as $combo) {
                    foreach ($axis['values'] as $val) {
                        $next[] = array_merge($combo, [$axis['name'] . ': ' . $val]);
                    }
                }
                $combos = $next;
            }
            $n = min(count($combos), count($prices));
            for ($i = 0; $i < $n; $i++) {
                // Restore readable order: original attribute position order in label
                $parts = array_reverse($combos[$i]);
                $label = implode(' / ', $parts);
                $length = null;
                if (preg_match('/(\d+)\s*(?:inches|inch|\")/i', $label, $m)) {
                    $length = $m[1] . '"';
                }
                $variants[] = [
                    'label' => $label,
                    'length' => $length,
                    'price' => $prices[$i],
                ];
            }
            return $variants;
        }
    }

    // Fallback: one variant per price
    foreach ($prices as $i => $price) {
        $variants[] = [
            'label' => count($prices) === 1 ? 'Standard' : ('Option ' . ($i + 1)),
            'length' => null,
            'price' => $price,
        ];
    }
    if ($variants === []) {
        $variants[] = ['label' => 'Standard', 'length' => null, 'price' => 0.0];
    }
    return $variants;
}

function wp_map_category_id(array $product, array $catBySlug): ?int
{
    $slugs = [];
    foreach ($product['cats'] as $c) {
        if ($c['domain'] === 'product_cat') {
            $slugs[] = $c['slug'];
        }
    }
    $title = mb_strtolower($product['title'] . ' ' . $product['slug']);
    if (in_array('crochet', $slugs, true) || str_contains($title, 'crochet')) {
        return $catBySlug['crochet'] ?? null;
    }
    if (str_contains($title, 'color add-on') || str_contains($title, 'colour add-on') || $product['slug'] === 'professional-hair-color-add-on') {
        return $catBySlug['color'] ?? null;
    }
    if (
        str_contains($title, 'unit')
        || str_contains($title, 'wig')
        || str_contains($title, 'closure')
        || str_contains($title, 'frontal')
        || str_contains($title, 'u-part')
        || str_contains($title, 'v-part')
    ) {
        return $catBySlug['wigs'] ?? null;
    }
    return $catBySlug['bundles'] ?? null;
}

// Ensure base categories exist
$needed = [
    'wigs' => 'Wigs & Units',
    'bundles' => 'Bundles',
    'crochet' => 'Crochet',
    'color' => 'Color Edit',
];
$catBySlug = [];
foreach (db()->query('SELECT id, slug FROM categories')->fetchAll() as $row) {
    $catBySlug[$row['slug']] = (int) $row['id'];
}
foreach ($needed as $slug => $name) {
    if (!isset($catBySlug[$slug])) {
        db()->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?,?,?,?)')
            ->execute([$name, $slug, '', count($catBySlug)]);
        $catBySlug[$slug] = (int) db()->lastInsertId();
        echo "Created category {$slug}\n";
    }
}

$pdo = db();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Clearing existing storefront products…\n";
// Order matters for FKs
foreach (['cart_items', 'order_items', 'wishlists', 'reviews', 'product_variants', 'products'] as $table) {
    try {
        if ($driver === 'sqlite') {
            $pdo->exec('DELETE FROM ' . $table);
        } else {
            $pdo->exec('DELETE FROM `' . $table . '`');
        }
    } catch (Throwable $e) {
        // table may not exist
    }
}
if ($driver === 'sqlite') {
    try {
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('products','product_variants','reviews','wishlists')");
    } catch (Throwable $e) {
    }
}

$insertProduct = $pdo->prepare(
    'INSERT INTO products (
        category_id, name, slug, short_description, description, base_price, compare_at_price,
        image, gallery, video, is_featured, is_active, on_sale, rating, review_count,
        seo_title, seo_description, focus_keyword, image_alt, faq_json
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$insertVariant = $pdo->prepare(
    'INSERT INTO product_variants (product_id, sku, label, option_length, price, stock, is_active)
     VALUES (?,?,?,?,?,?,1)'
);

$featuredSlugs = [
    'fro-kinky-curly-wefted-bundles-100-130g',
    'afro-kinky-curly-wefted-bundles',
    'afro-kinky-curly-clip-ins',
    'the-emefa-unit-200-density-6x6-hd-lace-closure-available-in-s-m-l-caps',
    'ohemaa-unit-queen-unit-4b-4c-hair-200-density-3-bundles-13x4-hd-lace-frontal',
];

$imported = 0;
$variantCount = 0;
$imageFails = 0;

foreach ($products as $product) {
    $slug = $product['slug'];
    // Cleaner storefront slugs for well-known products (content/titles still come from WP).
    $slugMap = [
        'fro-kinky-curly-wefted-bundles-100-130g' => 'afro-kinky-curly-wefted-bundles',
        'afrokinky-coily-bundles-100-130g' => 'afro-kinky-coily-wefted-bundles',
        'afro-kinky-curly-clip-ins' => 'afro-kinky-clip-in-set',
        'rich-auntie-kinky-straight-bundles-100-130g' => 'rich-auntie-kinky-straight-bundles',
        'the-siren-curly-bundles-3a-3b-100g' => 'the-siren-curly-bundles',
        'the-emefa-unit-200-density-6x6-hd-lace-closure-available-in-s-m-l-caps' => 'the-emefa-unit',
        'the-hollywood-unit-200-density-6x6-hd-lace-closure-available-in-s-m-l-caps' => 'the-hollywood-unit',
        'the-natural-queen-unit-200-density-6x6-hd-lace-closure-available-in-s-m-l-caps' => 'the-natural-queen-unit',
        'the-bombshell-curl-200-density-6x6-hd-lace-closure-available-in-s-m-l-caps' => 'the-bombshell-curl',
        'ohemaa-unit-queen-unit-4b-4c-hair-200-density-3-bundles-13x4-hd-lace-frontal' => 'ohemaa-queen-unit',
        'afro-kinky-curly-feather-crochet-100g4b-4c-copy' => 'kinky-straight-feather-crochet',
        'kinky-straight-feather-crochet-100gblowout-texture-copy' => 'exotic-afro-kinky-curly-feather-crochet',
        'bundle-deals' => 'exclusive-bundle-deals',
        'soft-life-tight-curly-bundles-100-300g' => 'soft-life-tight-curly-bundles',
        'silky-kinky-straight-wefted-bundles-100-130g' => 'silky-kinky-straight-bundles',
        'silky-kinky-straight-v-part-wig-three-bundles-200-density' => 'silky-kinky-straight-v-part-wig',
        'the-it-girl-curly-wefted-bundles-100-130g-2' => 'the-it-girl-curly-bundles',
        'afrokinkycurly-5x5-hd-lace-closure-120-density' => 'afro-kinky-5x5-hd-lace-closure',
        'afro-kinky-curly-wrap-around-ponytail' => 'afro-kinky-wrap-around-ponytail',
        'afro-kinky-curly-bulk-hair4b-4c-texture' => 'afro-kinky-curly-bulk-hair',
        'afroskinky-curly-u-part-wig' => 'afro-kinky-curly-u-part-wig',
    ];
    $finalSlug = $slugMap[$slug] ?? $slug;
    // Avoid collisions
    $baseSlug = $finalSlug;
    $i = 2;
    while (true) {
        $check = $pdo->prepare('SELECT 1 FROM products WHERE slug = ?');
        $check->execute([$finalSlug]);
        if (!$check->fetchColumn()) {
            break;
        }
        $finalSlug = $baseSlug . '-' . $i;
        $i++;
    }

    $variants = wp_build_variants($product);
    $basePrice = min(array_column($variants, 'price') ?: [0]);
    $short = wp_html_to_text($product['excerpt']);
    if ($short === '') {
        $short = mb_substr(wp_html_to_text($product['content']), 0, 220);
    }
    $short = mb_substr($short, 0, 500);
    $description = wp_html_to_text($product['content']);
    if ($description === '') {
        $description = $short;
    }

    // Images
    $thumbId = (int) (wp_meta_list($product['meta'], '_thumbnail_id')[0] ?? 0);
    $galleryIds = [];
    $galleryRaw = wp_meta_list($product['meta'], '_product_image_gallery')[0] ?? '';
    if ($galleryRaw !== '') {
        foreach (explode(',', $galleryRaw) as $gid) {
            $gid = (int) trim($gid);
            if ($gid > 0) {
                $galleryIds[] = $gid;
            }
        }
    }
    $imageRel = null;
    if ($thumbId && isset($attachments[$thumbId])) {
        $imageRel = wp_download_image($attachments[$thumbId]['url'], $imgDir, $imgDirRel);
        if (!$imageRel) {
            $imageFails++;
            echo "  ! image fail thumb {$thumbId} for {$product['title']}\n";
        }
    }
    $galleryRels = [];
    foreach ($galleryIds as $gid) {
        if (!isset($attachments[$gid])) {
            continue;
        }
        $rel = wp_download_image($attachments[$gid]['url'], $imgDir, $imgDirRel);
        if ($rel && $rel !== $imageRel) {
            $galleryRels[] = $rel;
        } elseif (!$rel) {
            $imageFails++;
        }
    }
    if (!$imageRel && $galleryRels) {
        $imageRel = array_shift($galleryRels);
    }
    if (!$imageRel) {
        $imageRel = 'assets/images/products/wp/Claudia.jpg';
    }

    $catId = wp_map_category_id($product, $catBySlug);
    $isFeatured = in_array($slug, $featuredSlugs, true) || in_array($finalSlug, $featuredSlugs, true) ? 1 : 0;
    $onSale = str_contains(mb_strtolower($product['title']), 'bundle deal') || $slug === 'bundle-deals' ? 1 : 0;
    $compareAt = null;
    if ($onSale && $basePrice > 0) {
        $compareAt = round($basePrice * 1.15, 2);
    }

    $seoTitle = mb_substr($product['title'], 0, 70);
    $seoDesc = mb_substr($short, 0, 320);

    $insertProduct->execute([
        $catId,
        mb_substr($product['title'], 0, 200),
        $finalSlug,
        $short,
        $description,
        $basePrice,
        $compareAt,
        $imageRel,
        $galleryRels ? json_encode(array_values($galleryRels), JSON_UNESCAPED_SLASHES) : null,
        null,
        $isFeatured,
        1,
        $onSale,
        5.0,
        0,
        $seoTitle,
        $seoDesc,
        null,
        $product['title'],
        null,
    ]);
    $productId = (int) $pdo->lastInsertId();

    foreach ($variants as $vi => $v) {
        $sku = 'WP-' . $product['id'] . '-' . ($vi + 1);
        $insertVariant->execute([
            $productId,
            $sku,
            mb_substr($v['label'], 0, 120),
            $v['length'],
            $v['price'],
            25,
        ]);
        $variantCount++;
    }

    $imported++;
    echo "✓ {$product['title']} → /product/{$finalSlug} (" . count($variants) . " variants)\n";
}

echo "\nDone. Products: {$imported}, variants: {$variantCount}, image failures: {$imageFails}\n";
echo "Open /shop to review.\n";
