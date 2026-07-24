<?php
declare(strict_types=1);

/** Add SEO columns to products, categories, and blog_posts. */
function ensure_seo_schema(PDO $pdo, string $driver): void
{
    $productCols = [
        'seo_title' => 'VARCHAR(70) NULL',
        'seo_description' => 'VARCHAR(320) NULL',
        'focus_keyword' => 'VARCHAR(80) NULL',
        'image_alt' => 'VARCHAR(160) NULL',
        'faq_json' => 'TEXT NULL',
    ];
    $categoryCols = [
        'seo_title' => 'VARCHAR(70) NULL',
        'seo_description' => 'VARCHAR(320) NULL',
        'focus_keyword' => 'VARCHAR(80) NULL',
        'intro_html' => 'TEXT NULL',
    ];
    $blogCols = [
        'seo_title' => 'VARCHAR(70) NULL',
        'seo_description' => 'VARCHAR(320) NULL',
        'focus_keyword' => 'VARCHAR(80) NULL',
    ];

    try {
        if ($driver === 'mysql') {
            seo_add_mysql_columns($pdo, 'products', $productCols);
            seo_add_mysql_columns($pdo, 'categories', $categoryCols);
            seo_add_mysql_columns($pdo, 'blog_posts', $blogCols);
            return;
        }
        seo_add_sqlite_columns($pdo, 'products', $productCols);
        seo_add_sqlite_columns($pdo, 'categories', $categoryCols);
        seo_add_sqlite_columns($pdo, 'blog_posts', $blogCols);
    } catch (Throwable $e) {
        // Non-fatal
    }
}

/** @param array<string,string> $cols */
function seo_add_mysql_columns(PDO $pdo, string $table, array $cols): void
{
    $existing = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $name => $def) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . $name . '` ' . $def);
        }
    }
}

/** @param array<string,string> $cols */
function seo_add_sqlite_columns(PDO $pdo, string $table, array $cols): void
{
    $info = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    $existing = array_map(static fn($c) => (string) ($c['name'] ?? ''), $info);
    foreach ($cols as $name => $def) {
        if (!in_array($name, $existing, true)) {
            // SQLite ignores length in VARCHAR; TEXT is fine
            $sqliteDef = str_contains(strtoupper($def), 'TEXT') ? 'TEXT' : 'TEXT';
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $sqliteDef);
        }
    }
}

function seo_store_name(): string
{
    return (string) (setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene');
}

function seo_title_pattern(): string
{
    $pattern = trim((string) setting('seo_title_pattern', '{page} | {store}'));
    return $pattern !== '' ? $pattern : '{page} | {store}';
}

function seo_format_title(string $pageTitle): string
{
    $store = seo_store_name();
    $page = trim($pageTitle);
    if ($page === '') {
        return $store;
    }
    // Avoid double-appending store name
    if (str_contains(mb_strtolower($page), mb_strtolower($store))) {
        return $page;
    }
    return str_replace(
        ['{page}', '{store}'],
        [$page, $store],
        seo_title_pattern()
    );
}

/**
 * Apply SEO overrides onto template variables (by reference via return array).
 *
 * @param array{
 *   title?:string,
 *   description?:string,
 *   canonical?:string,
 *   og_image?:string,
 *   og_image_alt?:string,
 *   og_type?:string,
 *   robots?:string,
 *   json_ld?:array|string|null
 * } $overrides
 * @return array{pageTitle:string,pageDescription:string,canonical:string,ogImage:?string,ogImageAlt:string,ogType:string,robots:string,jsonLd:mixed}
 */
function seo_apply(array $overrides = []): array
{
    $logo = (string) setting('logo_path', 'assets/images/logo.png');
    $defaultDesc = (string) setting('meta_description', 'Luxury hair for every curl story.');
    $ogDefault = (string) setting('og_image', $logo);

    $titleRaw = trim((string) ($overrides['title'] ?? ''));
    $desc = trim((string) ($overrides['description'] ?? ''));
    if ($desc === '') {
        $desc = $defaultDesc;
    }

    return [
        'pageTitle' => seo_format_title($titleRaw !== '' ? $titleRaw : seo_store_name()),
        'pageDescription' => mb_substr($desc, 0, 320),
        'canonical' => (string) ($overrides['canonical'] ?? url()),
        'ogImage' => $overrides['og_image'] ?? $ogDefault,
        'ogImageAlt' => trim((string) ($overrides['og_image_alt'] ?? '')),
        'ogType' => (string) ($overrides['og_type'] ?? 'website'),
        'robots' => (string) ($overrides['robots'] ?? 'index, follow'),
        'jsonLd' => $overrides['json_ld'] ?? null,
    ];
}

/** @param list<array{name:string,url:string}> $items */
function seo_breadcrumbs(array $items): array
{
    $list = [];
    $pos = 1;
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        $itemUrl = trim((string) ($item['url'] ?? ''));
        if ($name === '' || $itemUrl === '') {
            continue;
        }
        $list[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $name,
            'item' => $itemUrl,
        ];
    }
    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

/** @return list<array{question:string,answer:string}> */
function seo_parse_faq(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $q = trim((string) ($row['question'] ?? ''));
        $a = trim((string) ($row['answer'] ?? ''));
        if ($q === '' || $a === '') {
            continue;
        }
        $out[] = ['question' => $q, 'answer' => $a];
        if (count($out) >= 8) {
            break;
        }
    }
    return $out;
}

function seo_faq_jsonld(array $faqs): ?array
{
    if ($faqs === []) {
        return null;
    }
    $entities = [];
    foreach ($faqs as $faq) {
        $entities[] = [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ];
    }
    return [
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

/**
 * @param array<string,mixed> $product
 * @param list<string> $images relative paths
 */
function seo_product_jsonld(array $product, string $canonical, float $price, bool $inStock, array $images = []): array
{
    $logo = (string) setting('logo_path', 'assets/images/logo.png');
    $imgUrls = [];
    foreach ($images as $img) {
        $img = trim((string) $img);
        if ($img !== '') {
            $imgUrls[] = asset($img);
        }
    }
    if ($imgUrls === []) {
        $imgUrls[] = asset($logo);
    }

    $ld = [
        '@type' => 'Product',
        'name' => (string) $product['name'],
        'description' => strip_tags((string) ($product['seo_description'] ?? $product['short_description'] ?? $product['description'] ?? $product['name'])),
        'sku' => 'CD-' . (int) ($product['id'] ?? 0),
        'image' => $imgUrls,
        'brand' => ['@type' => 'Brand', 'name' => seo_store_name()],
        'offers' => [
            '@type' => 'Offer',
            'url' => $canonical,
            'priceCurrency' => 'GBP',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ],
    ];
    if ((int) ($product['review_count'] ?? 0) > 0) {
        $ld['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $product['rating'],
            'reviewCount' => (string) $product['review_count'],
        ];
    }
    return $ld;
}

function seo_organization_jsonld(): array
{
    $logo = (string) setting('logo_path', 'assets/images/logo.png');
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => seo_store_name(),
        'url' => url(),
        'logo' => asset($logo),
        'email' => setting('contact_email', ''),
        'telephone' => setting('contact_phone', ''),
        'sameAs' => array_values(array_filter([
            setting('social_instagram', ''),
            setting('social_tiktok', ''),
            setting('social_facebook', ''),
        ])),
    ];
    $street = trim((string) setting('store_address', ''));
    $city = trim((string) setting('store_city', ''));
    $country = trim((string) setting('store_country', ''));
    if ($street !== '' || $city !== '' || $country !== '') {
        $org['@type'] = 'LocalBusiness';
        $org['address'] = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street !== '' ? $street : null,
            'addressLocality' => $city !== '' ? $city : null,
            'addressCountry' => $country !== '' ? $country : null,
        ], static fn($v) => $v !== null && $v !== '');
    }
    return $org;
}

/**
 * Score an SEO entity 0–100.
 *
 * @param array{title?:string,description?:string,image?:string,focus_keyword?:string,has_schema?:bool} $entity
 */
function seo_score(array $entity): int
{
    $score = 0;
    $title = trim((string) ($entity['title'] ?? ''));
    $desc = trim((string) ($entity['description'] ?? ''));
    $image = trim((string) ($entity['image'] ?? ''));
    $kw = trim((string) ($entity['focus_keyword'] ?? ''));
    $titleLen = mb_strlen($title);
    $descLen = mb_strlen($desc);

    if ($title !== '' && $titleLen >= 30 && $titleLen <= 60) {
        $score += 25;
    } elseif ($title !== '') {
        $score += 10;
    }

    if ($desc !== '' && $descLen >= 70 && $descLen <= 160) {
        $score += 25;
    } elseif ($desc !== '') {
        $score += 10;
    }

    if ($image !== '') {
        $score += 20;
    }

    if ($kw !== '') {
        $hay = mb_strtolower($title . ' ' . $desc);
        if (str_contains($hay, mb_strtolower($kw))) {
            $score += 15;
        }
    } else {
        $score += 5; // no keyword required
    }

    if (!empty($entity['has_schema'])) {
        $score += 15;
    } else {
        $score += 5;
    }

    return min(100, $score);
}

function seo_product_alt(array $product): string
{
    $alt = trim((string) ($product['image_alt'] ?? ''));
    return $alt !== '' ? $alt : (string) ($product['name'] ?? 'Product');
}
