<?php
/**
 * One-off importer for a WooCommerce WXR (WordPress export) file.
 *
 * Usage: php database/import_wordpress.php /absolute/path/to/export.xml
 *
 * Imports products (with variants from the "size" attribute, featured image,
 * and gallery images), downloads images locally, and marks them active.
 * Products are upserted by slug; demo products not present in the export are
 * deactivated so the shop shows the real catalogue.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$xmlPath = $argv[1] ?? null;
if (!$xmlPath || !file_exists($xmlPath)) {
    fwrite(STDERR, "XML file not found. Usage: php database/import_wordpress.php <file.xml>\n");
    exit(1);
}

$pdo = db();
$imageDirRel = 'assets/images/products/wp';
$imageDirAbs = ROOT_PATH . '/' . $imageDirRel;
if (!is_dir($imageDirAbs)) {
    mkdir($imageDirAbs, 0775, true);
}

$raw = file_get_contents($xmlPath);
$xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
if ($xml === false) {
    fwrite(STDERR, "Failed to parse XML.\n");
    exit(1);
}
$ns = $xml->getNamespaces(true);

/** @return string */
function first_str($v): string
{
    return trim((string) $v);
}

/** Extract a WooCommerce serialized attribute value list for the variation attribute. */
function parse_size_options(string $serialized): array
{
    // Grab the value string of the first attribute flagged is_variation.
    if (!preg_match('/"value";s:\d+:"(.*?)";s:8:"position"/s', $serialized, $m)) {
        if (!preg_match('/"value";s:\d+:"(.*?)"/s', $serialized, $m)) {
            return [];
        }
    }
    $parts = array_map('trim', explode('|', $m[1]));
    return array_values(array_filter($parts, fn($p) => $p !== ''));
}

function inches_from(string $label): ?int
{
    return preg_match('/(\d+)/', $label, $m) ? (int) $m[1] : null;
}

function map_category(PDO $pdo, string $title): int
{
    static $cache = [];
    if (!$cache) {
        foreach ($pdo->query('SELECT id, slug FROM categories')->fetchAll() as $c) {
            $cache[$c['slug']] = (int) $c['id'];
        }
    }
    $t = strtolower($title);
    $slug = 'bundles';
    if (str_contains($t, 'crochet')) {
        $slug = 'crochet';
    } elseif (str_contains($t, 'color') || str_contains($t, 'colour')) {
        $slug = 'color';
    } elseif (preg_match('/\b(unit|wig|closure|frontal|v-part|u-part|lace)\b/', $t)) {
        $slug = 'wigs';
    }
    return $cache[$slug] ?? (int) ($cache['bundles'] ?? array_values($cache)[0]);
}

function download_image(string $url, string $dirAbs, string $dirRel): ?string
{
    $clean = strtok($url, '?');
    $base = basename((string) $clean);
    $base = preg_replace('/[^A-Za-z0-9._-]/', '-', $base);
    if ($base === '' || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $base)) {
        return null;
    }
    $destAbs = $dirAbs . '/' . $base;
    $destRel = $dirRel . '/' . $base;
    if (file_exists($destAbs) && filesize($destAbs) > 0) {
        return $destRel;
    }
    $ctx = stream_context_create([
        'http' => ['timeout' => 25, 'user_agent' => 'Mozilla/5.0 CD-Importer', 'follow_location' => 1],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 100) {
        return null;
    }
    file_put_contents($destAbs, $data);
    return $destRel;
}

// 1) Build attachment id -> URL map.
$attachments = [];
foreach ($xml->channel->item as $item) {
    $wp = $item->children($ns['wp']);
    if (first_str($wp->post_type) !== 'attachment') {
        continue;
    }
    $id = first_str($wp->post_id);
    $url = first_str($wp->attachment_url);
    if ($id !== '' && $url !== '') {
        $attachments[$id] = $url;
    }
}
echo 'Attachments found: ' . count($attachments) . "\n";

// 2) Iterate products.
$importedSlugs = [];
$productCount = 0;
$variantCount = 0;
$imageCount = 0;

$findBySlug = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
$updateProduct = $pdo->prepare(
    'UPDATE products SET category_id=?, name=?, short_description=?, description=?, base_price=?, image=?, gallery=?, is_active=1, rating=?, review_count=? WHERE id=?'
);
$insertProduct = $pdo->prepare(
    'INSERT INTO products (category_id, name, slug, short_description, description, base_price, image, gallery, is_featured, is_active, rating, review_count) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)'
);
$deleteVariants = $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?');
$insertVariant = $pdo->prepare(
    'INSERT INTO product_variants (product_id, sku, label, option_length, price, stock, is_active) VALUES (?,?,?,?,?,?,1)'
);

foreach ($xml->channel->item as $item) {
    $wp = $item->children($ns['wp']);
    if (first_str($wp->post_type) !== 'product' || first_str($wp->status) !== 'publish') {
        continue;
    }

    $title = trim((string) $item->title);
    $slug = first_str($wp->post_name);
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    }
    $slug = trim($slug, '-');

    $content = trim((string) $item->children($ns['content'])->encoded);
    $excerpt = trim((string) $item->children($ns['excerpt'])->encoded);
    $shortDesc = $excerpt !== '' ? $excerpt : mb_substr(trim(strip_tags($content)), 0, 220);
    $description = $content !== '' ? trim(strip_tags($content, '<p><br><strong><em><ul><li>')) : $shortDesc;

    // Meta
    $meta = [];
    $prices = [];
    foreach ($wp->postmeta as $pm) {
        $key = first_str($pm->meta_key);
        $val = first_str($pm->meta_value);
        if ($key === '_price') {
            if (is_numeric($val)) {
                $prices[] = (float) $val;
            }
            continue;
        }
        // keep the first occurrence of other keys
        if (!isset($meta[$key])) {
            $meta[$key] = $val;
        }
    }

    $sizes = isset($meta['_product_attributes']) ? parse_size_options($meta['_product_attributes']) : [];
    $rating = (float) ($meta['_wc_average_rating'] ?? 0);
    if ($rating <= 0) {
        $rating = 5.0;
    }
    $reviews = (int) ($meta['_wc_review_count'] ?? 0);

    // Images
    $imageRel = null;
    if (!empty($meta['_thumbnail_id']) && isset($attachments[$meta['_thumbnail_id']])) {
        $imageRel = download_image($attachments[$meta['_thumbnail_id']], $imageDirAbs, $imageDirRel);
        if ($imageRel) {
            $imageCount++;
        }
    }
    $gallery = [];
    if (!empty($meta['_product_image_gallery'])) {
        foreach (explode(',', $meta['_product_image_gallery']) as $gid) {
            $gid = trim($gid);
            if ($gid !== '' && isset($attachments[$gid])) {
                $rel = download_image($attachments[$gid], $imageDirAbs, $imageDirRel);
                if ($rel) {
                    $gallery[] = $rel;
                    $imageCount++;
                }
            }
        }
    }
    if ($imageRel === null && $gallery) {
        $imageRel = $gallery[0];
    }

    // Pricing ladder
    $uniquePrices = array_values(array_unique($prices));
    sort($uniquePrices, SORT_NUMERIC);
    $basePrice = $uniquePrices[0] ?? 0.0;

    $categoryId = map_category($pdo, $title);
    $galleryJson = $gallery ? json_encode($gallery) : null;

    // Upsert product
    $findBySlug->execute([$slug]);
    $existingId = $findBySlug->fetchColumn();
    if ($existingId) {
        $productId = (int) $existingId;
        $updateProduct->execute([
            $categoryId, $title, $shortDesc, $description, $basePrice,
            $imageRel, $galleryJson, $rating, $reviews, $productId,
        ]);
    } else {
        $insertProduct->execute([
            $categoryId, $title, $slug, $shortDesc, $description, $basePrice,
            $imageRel, $galleryJson, 1, $rating, $reviews,
        ]);
        $productId = (int) $pdo->lastInsertId();
    }
    $importedSlugs[] = $slug;
    $productCount++;

    // Variants from size options; fall back to a single default variant.
    $deleteVariants->execute([$productId]);
    if ($sizes) {
        usort($sizes, fn($a, $b) => (inches_from($a) ?? 0) <=> (inches_from($b) ?? 0));
        foreach ($sizes as $i => $size) {
            $price = $uniquePrices[$i] ?? ($basePrice + $i * 20);
            $inch = inches_from($size);
            $optLength = $inch !== null ? $inch . '"' : $size;
            $sku = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $slug), 0, 8)) . '-' . ($inch ?? ($i + 1));
            $insertVariant->execute([$productId, $sku, $size, $optLength, $price, 20]);
            $variantCount++;
        }
    } else {
        $sku = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $slug), 0, 8)) . '-STD';
        $insertVariant->execute([$productId, $sku, 'Standard', null, $basePrice, 20]);
        $variantCount++;
    }

    echo sprintf("  ✓ %-52s %2d variants  img:%s  gallery:%d\n",
        mb_substr($title, 0, 52), $sizes ? count($sizes) : 1, $imageRel ? 'yes' : 'no', count($gallery));
}

// 3) Deactivate demo products not present in the import.
if ($importedSlugs) {
    $placeholders = implode(',', array_fill(0, count($importedSlugs), '?'));
    $deact = $pdo->prepare("UPDATE products SET is_active = 0 WHERE slug NOT IN ($placeholders)");
    $deact->execute($importedSlugs);
    $deactivated = $deact->rowCount();
} else {
    $deactivated = 0;
}

echo "\n";
echo "Products imported/updated: $productCount\n";
echo "Variants created:          $variantCount\n";
echo "Images downloaded:         $imageCount\n";
echo "Demo products deactivated: $deactivated\n";
echo "Done.\n";
