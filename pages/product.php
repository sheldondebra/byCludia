<?php
declare(strict_types=1);

$slug = trim((string) get('slug', ''));
$stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('index.php?page=shop');
}

if (request_method() === 'POST' && post('action') === 'review') {
    if (verify_csrf(post('csrf_token'))) {
        $u = current_user();
        $rating = max(1, min(5, (int) post('rating', 5)));
        $body = trim((string) post('body'));
        $authorName = $u['name'] ?? trim((string) post('author_name'));
        $title = trim((string) post('title'));
        if ($body !== '' && $authorName !== '') {
            db()->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, title, body, is_approved) VALUES (?,?,?,?,?,?,0)')
                ->execute([$product['id'], $u['id'] ?? null, $authorName, $rating, $title, $body]);
            flash('success', 'Thank you! Your review has been submitted and will appear once approved.');
        } else {
            flash('error', 'Please add your name and a review.');
        }
    }
    redirect('index.php?page=product&slug=' . urlencode($product['slug']));
}

$vStmt = db()->prepare('SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY price ASC');
$vStmt->execute([$product['id']]);
$variants = $vStmt->fetchAll();
$defaultVariant = $variants[0] ?? null;

$reviewsStmt = db()->prepare('SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY id DESC');
$reviewsStmt->execute([$product['id']]);
$reviews = $reviewsStmt->fetchAll();

$related = [];
if (!empty($product['category_id'])) {
    $relStmt = db()->prepare('SELECT * FROM products WHERE category_id = ? AND id <> ? AND is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 4');
    $relStmt->execute([$product['category_id'], $product['id']]);
    $related = $relStmt->fetchAll();
}

$isWishlisted = wishlist_has((int) $product['id']);

$gallery = [];
if (!empty($product['gallery'])) {
    $decoded = json_decode((string) $product['gallery'], true);
    if (is_array($decoded)) {
        $gallery = $decoded;
    }
}
$mainImage = null;
if (!empty($product['image']) && file_exists(ROOT_PATH . '/' . $product['image'])) {
    $mainImage = $product['image'];
} elseif ($gallery && file_exists(ROOT_PATH . '/' . $gallery[0])) {
    $mainImage = $gallery[0];
}
$thumbs = [];
foreach (array_merge($mainImage ? [$mainImage] : [], $gallery) as $img) {
    if (file_exists(ROOT_PATH . '/' . $img) && !in_array($img, $thumbs, true)) {
        $thumbs[] = $img;
    }
}

// Product video (uploaded file, direct URL, YouTube, or Vimeo)
$rawVideo = (string) ($product['video'] ?? '');
$videoMeta = parse_product_video($rawVideo);
$hasProductVideo = $videoMeta['type'] !== 'none';
$productVideo = $videoMeta['type'] === 'file' ? $videoMeta['src'] : '';
$productVideoEmbed = $videoMeta['embed'] ?? null;
$productVideoType = $videoMeta['type'];

$pageTitle = $product['name'] . ' – Hair by Claudia Darlene';
$pageDescription = $product['short_description'] ?? $product['name'];

// --- SEO ---
$canonical = url('product/' . $product['slug']);
$seoTitle = trim((string) ($product['seo_title'] ?? ''));
$seoDesc = trim((string) ($product['seo_description'] ?? ''));
$pageTitle = seo_format_title($seoTitle !== '' ? $seoTitle : (string) $product['name']);
$pageDescription = $seoDesc !== '' ? $seoDesc : (string) ($product['short_description'] ?? $product['name']);
$ogType = 'product';
$productAlt = seo_product_alt($product);
$ogImageAlt = $productAlt;
if ($mainImage) {
    $ogImage = $mainImage;
}
$lowestPrice = $defaultVariant['price'] ?? ($product['price'] ?? 0);
$inStock = false;
foreach ($variants as $v) {
    if ((int) $v['stock'] > 0) { $inStock = true; break; }
}
$productFaqs = seo_parse_faq(isset($product['faq_json']) ? (string) $product['faq_json'] : null);
$productLd = seo_product_jsonld($product, $canonical, (float) $lowestPrice, $inStock, $thumbs ?: ($mainImage ? [$mainImage] : []));
$graph = [
    $productLd,
    seo_breadcrumbs([
        ['name' => 'Home', 'url' => url()],
        ['name' => 'Shop', 'url' => url('shop')],
        ['name' => (string) $product['name'], 'url' => $canonical],
    ]),
];
if ($faqLd = seo_faq_jsonld($productFaqs)) {
    $graph[] = $faqLd;
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => $graph,
];

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-12 sm:py-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-16">
    <div class="reveal">
      <?php if ($mainImage): ?>
        <div class="relative group" data-gallery>
          <div id="product-zoom" class="aspect-[4/5] rounded-[28px] overflow-hidden bg-white shadow-soft cursor-zoom-in">
            <img id="product-main-image" src="<?= e(asset($mainImage)) ?>" alt="<?= e($productAlt) ?>" class="w-full h-full object-cover transition-transform duration-150 ease-out will-change-transform">
            <?php if ($hasProductVideo && $productVideoType === 'file'): ?>
              <video id="product-main-video" class="hidden absolute inset-0 w-full h-full object-cover bg-black" controls playsinline preload="metadata" poster="<?= e(asset($mainImage)) ?>">
                <source src="<?= e($productVideo) ?>" type="video/mp4">
              </video>
            <?php elseif ($hasProductVideo && $productVideoEmbed): ?>
              <div id="product-main-embed" class="hidden absolute inset-0 bg-black">
                <iframe data-embed-src="<?= e($productVideoEmbed) ?>" src="" title="<?= e($product['name']) ?> video" class="w-full h-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
              </div>
            <?php endif; ?>
          </div>
          <?php if (count($thumbs) > 1): ?>
            <button type="button" data-gallery-prev aria-label="Previous image" class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/85 backdrop-blur border border-brand-ink/10 text-brand-ink flex items-center justify-center shadow-soft opacity-0 group-hover:opacity-100 transition hover:bg-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" data-gallery-next aria-label="Next image" class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/85 backdrop-blur border border-brand-ink/10 text-brand-ink flex items-center justify-center shadow-soft opacity-0 group-hover:opacity-100 transition hover:bg-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-brand-ink/70 text-white text-[11px] tracking-wide backdrop-blur"><span data-gallery-current>1</span> / <?= count($thumbs) ?></div>
          <?php endif; ?>
        </div>
        <?php if (count($thumbs) > 1 || $hasProductVideo): ?>
          <div class="mt-4 grid grid-cols-5 gap-3">
            <?php foreach ($thumbs as $i => $img): ?>
              <button type="button" data-thumb="<?= e(asset($img)) ?>" data-thumb-index="<?= $i ?>"
                class="aspect-square rounded-2xl overflow-hidden border <?= $i === 0 ? 'border-brand-ink' : 'border-brand-ink/10' ?> hover:border-brand-ink transition">
                <img src="<?= e(asset($img)) ?>" alt="<?= e($product['name']) ?> view <?= $i + 1 ?>" class="w-full h-full object-cover">
              </button>
            <?php endforeach; ?>
            <?php if ($hasProductVideo): ?>
              <button type="button" data-video-thumb aria-label="Play product video"
                class="relative aspect-square rounded-2xl overflow-hidden border border-brand-ink/10 hover:border-brand-ink transition bg-black">
                <img src="<?= e(asset($mainImage)) ?>" alt="<?= e($product['name']) ?> video" class="w-full h-full object-cover opacity-60">
                <span class="absolute inset-0 flex items-center justify-center">
                  <span class="w-9 h-9 rounded-full bg-white/90 flex items-center justify-center">
                    <svg class="w-4 h-4 text-brand-ink ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  </span>
                </span>
              </button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($hasProductVideo): ?>
        <div class="aspect-[4/5] rounded-[28px] overflow-hidden bg-black shadow-soft">
          <?php if ($productVideoType === 'file'): ?>
            <video class="w-full h-full object-cover" controls playsinline preload="metadata">
              <source src="<?= e($productVideo) ?>" type="video/mp4">
            </video>
          <?php else: ?>
            <iframe src="<?= e((string) $productVideoEmbed) ?>" title="<?= e($product['name']) ?> video" class="w-full h-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="aspect-[4/5] rounded-[28px] overflow-hidden bg-gradient-to-br from-brand-mist via-brand-blush/50 to-[#e8c4a8] shadow-soft flex items-end p-8">
          <h2 class="font-display text-4xl text-brand-ink/80 leading-tight max-w-sm"><?= e($product['name']) ?></h2>
        </div>
      <?php endif; ?>
    </div>

    <div class="reveal lg:pt-4">
      <?php if (!empty($product['category_name'])): ?>
        <p class="text-xs tracking-[0.22em] uppercase text-brand-soft mb-3"><?= e($product['category_name']) ?></p>
      <?php endif; ?>
      <h1 class="font-display text-4xl sm:text-5xl mb-3"><?= e($product['name']) ?></h1>
      <div class="flex items-center gap-3 mb-5">
        <div><?= stars((float) $product['rating']) ?></div>
        <span class="text-sm text-brand-soft">(<?= (int) $product['review_count'] ?> reviews)</span>
      </div>
      <div class="mb-5">
        <p id="display-price" class="text-2xl font-medium" data-base="<?= e((string) ($defaultVariant['price'] ?? $product['base_price'])) ?>">
          <?= money((float) ($defaultVariant['price'] ?? $product['base_price'])) ?>
        </p>
        <p id="price-qty-note" class="text-sm text-brand-soft mt-1 hidden" aria-live="polite"></p>
      </div>

      <div class="flex flex-wrap gap-2 mb-6">
        <button type="button"
          data-wishlist-toggle="<?= (int) $product['id'] ?>"
          aria-pressed="<?= $isWishlisted ? 'true' : 'false' ?>"
          aria-label="Add to wishlist"
          title="Add to wishlist"
          class="inline-flex items-center gap-2 rounded-full border border-brand-ink/15 bg-white px-4 py-2.5 text-sm hover:bg-brand-mist/60 transition <?= $isWishlisted ? 'text-rose-500 border-rose-200' : 'text-brand-ink' ?>">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="<?= $isWishlisted ? 'currentColor' : 'none' ?>" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21C12 21 4 13.9 4 8.8 4 6.1 6.1 4 8.8 4c1.6 0 3.1.8 3.2 2 .1-1.2 1.6-2 3.2-2C17.9 4 20 6.1 20 8.8c0 5.1-8 12.2-8 12.2z"/></svg>
          <span data-wishlist-label><?= $isWishlisted ? 'Wishlisted' : 'Wishlist' ?></span>
        </button>
        <button type="button"
          data-compare-toggle="<?= (int) $product['id'] ?>"
          aria-label="Add to compare"
          title="Add to compare"
          class="inline-flex items-center gap-2 rounded-full border border-brand-ink/15 bg-white/90 px-4 py-2.5 text-sm text-brand-ink hover:bg-brand-mist/60 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h2m6-16h2a2 2 0 012 2v12a2 2 0 01-2 2h-2m-3-18v20"/></svg>
          <span data-compare-label>Compare</span>
        </button>
        <a href="<?= e(url('index.php?page=compare')) ?>" class="inline-flex items-center gap-1.5 rounded-full px-3 py-2.5 text-xs tracking-[0.12em] uppercase text-brand-soft hover:text-brand-ink transition">
          View compare
        </a>
      </div>
      <?php
      $waNumber = whatsapp_number();
      $waDefaultVariant = (string) ($defaultVariant['label'] ?? '');
      $waDefaultPrice = money((float) ($defaultVariant['price'] ?? $product['base_price']));
      $waHref = $waNumber !== '' ? whatsapp_order_url([
          'name' => $product['name'],
          'variant' => $waDefaultVariant,
          'quantity' => 1,
          'price' => $waDefaultPrice,
          'url' => $canonical,
      ]) : '';
      ?>

      <?php if ($variants): ?>
        <form data-add-to-cart method="post" class="space-y-6">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
          <div>
            <label class="block text-xs tracking-[0.18em] uppercase text-brand-soft mb-3">Length</label>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($variants as $i => $v): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" data-price="<?= e((string) $v['price']) ?>" data-label="<?= e((string) $v['label']) ?>" class="peer sr-only" <?= $i === 0 ? 'checked' : '' ?> required>
                  <span class="inline-block px-4 py-2 rounded-full border border-brand-ink/15 text-sm peer-checked:bg-brand-ink peer-checked:text-white peer-checked:border-brand-ink transition"><?= e($v['label']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label for="qty" class="block text-xs tracking-[0.18em] uppercase text-brand-soft mb-3">Quantity</label>
            <input id="qty" type="number" name="quantity" min="1" value="1" class="w-24 rounded-full border border-brand-ink/15 bg-white px-4 py-2.5 text-sm">
          </div>
          <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <button type="submit" class="btn-ink inline-flex items-center justify-center gap-2 px-10 py-3.5 text-sm tracking-[0.12em] uppercase w-full sm:w-auto">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
              Add to Cart
            </button>
            <button type="button" data-product-buy-now class="btn-blush inline-flex items-center justify-center gap-2 px-10 py-3.5 text-sm tracking-[0.12em] uppercase w-full sm:w-auto">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
              Buy Now
            </button>
            <?php if ($waHref !== ''): ?>
              <a
                href="<?= e($waHref) ?>"
                target="_blank"
                rel="noopener"
                data-whatsapp-order
                data-wa-number="<?= e($waNumber) ?>"
                data-product-name="<?= e($product['name']) ?>"
                data-product-url="<?= e($canonical) ?>"
                data-store-name="<?= e((string) (setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene')) ?>"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-[#25D366] text-white px-8 py-3.5 text-sm tracking-[0.12em] uppercase font-medium hover:bg-[#1ebe57] transition w-full sm:w-auto"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
                Shop on WhatsApp
              </a>
            <?php endif; ?>
          </div>
        </form>
      <?php elseif ($waHref !== ''): ?>
        <div class="mt-6">
          <a
            href="<?= e($waHref) ?>"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center justify-center gap-2 rounded-full bg-[#25D366] text-white px-8 py-3.5 text-sm tracking-[0.12em] uppercase font-medium hover:bg-[#1ebe57] transition"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
            Shop on WhatsApp
          </a>
        </div>
      <?php endif; ?>

      <div class="mt-8 pt-6 border-t border-brand-ink/10">
        <?php
        $shareUrl = $canonical;
        $shareTitle = $product['name'];
        $shareImage = $mainImage ? asset($mainImage) : '';
        require ROOT_PATH . '/includes/partials/share.php';
        ?>
      </div>

      <div class="mt-8 pt-8 border-t border-brand-ink/10 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex items-center gap-3 rounded-2xl bg-brand-mist/60 border border-brand-ink/5 px-4 py-3">
          <span class="shrink-0 w-10 h-10 rounded-full bg-white text-brand-ink flex items-center justify-center shadow-soft">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
          </span>
          <div class="leading-tight">
            <p class="text-sm font-medium text-brand-ink">Worldwide shipping</p>
            <p class="text-xs text-brand-soft">Tracked & insured</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-brand-mist/60 border border-brand-ink/5 px-4 py-3">
          <span class="shrink-0 w-10 h-10 rounded-full bg-white text-brand-ink flex items-center justify-center shadow-soft">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M6 15h4m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          </span>
          <div class="leading-tight">
            <p class="text-sm font-medium text-brand-ink">Klarna &amp; Clearpay</p>
            <p class="text-xs text-brand-soft">Pay in 3 or 4</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-brand-mist/60 border border-brand-ink/5 px-4 py-3">
          <span class="shrink-0 w-10 h-10 rounded-full bg-white text-brand-ink flex items-center justify-center shadow-soft">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.5-3 7.9-7 9-4-1.1-7-4.5-7-9V7l7-4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12l1.8 1.8L15 10"/></svg>
          </span>
          <div class="leading-tight">
            <p class="text-sm font-medium text-brand-ink">Secure checkout</p>
            <p class="text-xs text-brand-soft">SSL encrypted</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($product['description'] ?? $product['short_description'])): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 sm:mt-16">
      <div class="border-t border-brand-ink/10 pt-10">
        <h2 class="font-display text-3xl sm:text-4xl mb-5">Description</h2>
        <div class="text-brand-soft leading-relaxed text-[17px] max-w-4xl whitespace-pre-line"><?= e($product['description'] ?? $product['short_description']) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($productFaqs): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
      <div class="border-t border-brand-ink/10 pt-10 max-w-3xl">
        <h2 class="font-display text-3xl sm:text-4xl mb-6">Questions &amp; answers</h2>
        <div class="space-y-3">
          <?php foreach ($productFaqs as $i => $faq): ?>
            <details class="group rounded-2xl border border-brand-ink/10 bg-white/70 px-5 py-4" <?= $i === 0 ? 'open' : '' ?>>
              <summary class="cursor-pointer list-none flex items-center justify-between gap-3 font-medium text-brand-ink">
                <span><?= e($faq['question']) ?></span>
                <span class="text-brand-soft text-lg leading-none group-open:rotate-45 transition">+</span>
              </summary>
              <p class="mt-3 text-sm text-brand-soft leading-relaxed"><?= e($faq['answer']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

<section class="pb-16 sm:pb-20">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="border-t border-brand-ink/10 pt-12">
      <div class="flex items-end justify-between gap-4 mb-8 flex-wrap">
        <div>
          <h2 class="font-display text-3xl sm:text-4xl">Customer Reviews</h2>
          <div class="flex items-center gap-2 mt-2">
            <?= stars((float) $product['rating']) ?>
            <span class="text-sm text-brand-soft"><?= number_format((float) $product['rating'], 1) ?> &middot; <?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></span>
          </div>
        </div>
        <button type="button" data-review-toggle class="btn-ink px-6 py-3 text-sm tracking-[0.12em] uppercase">Write a review</button>
      </div>

      <form method="post" data-review-form class="hidden bg-white/70 border border-brand-ink/5 rounded-3xl p-6 mb-10 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review">
        <div>
          <label class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-2">Your rating</label>
          <div class="flex gap-1" data-star-picker>
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <button type="button" data-star="<?= $s ?>" class="text-2xl leading-none text-brand-blushDeep">&#9733;</button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" value="5">
        </div>
        <?php if (!current_user()): ?>
          <input name="author_name" required placeholder="Your name" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
        <?php endif; ?>
        <input name="title" placeholder="Review title (optional)" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
        <textarea name="body" required rows="4" placeholder="Share your experience…" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm"></textarea>
        <button class="btn-ink px-8 py-3 text-sm tracking-[0.12em] uppercase">Submit review</button>
      </form>

      <?php if ($reviews): ?>
        <div class="space-y-6">
          <?php foreach ($reviews as $rv): ?>
            <div class="bg-white/60 rounded-2xl p-5 border border-brand-ink/5">
              <div class="flex items-center justify-between gap-3 mb-2">
                <div class="text-sm"><?= stars((float) $rv['rating']) ?></div>
                <span class="text-xs text-brand-soft"><?= e(date('d M Y', strtotime((string) $rv['created_at']))) ?></span>
              </div>
              <?php if (!empty($rv['title'])): ?><p class="font-medium mb-1"><?= e($rv['title']) ?></p><?php endif; ?>
              <p class="text-sm text-brand-soft leading-relaxed mb-2"><?= nl2br(e($rv['body'])) ?></p>
              <p class="text-xs tracking-[0.14em] uppercase text-brand-ink/50"><?= e($rv['author_name']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-brand-soft text-sm">No reviews yet — be the first to share your experience.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="font-display text-3xl sm:text-4xl mb-8 text-center">You may also like</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
      <?php foreach ($related as $product): ?>
        <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
(() => {
  const toggle = document.querySelector('[data-review-toggle]');
  const form = document.querySelector('[data-review-form]');
  if (toggle && form) {
    toggle.addEventListener('click', () => form.classList.toggle('hidden'));
  }
  const picker = document.querySelector('[data-star-picker]');
  if (picker) {
    const input = picker.parentElement.querySelector('input[name="rating"]');
    const stars = picker.querySelectorAll('[data-star]');
    const paint = (val) => stars.forEach((s) => s.classList.toggle('opacity-30', Number(s.dataset.star) > val));
    stars.forEach((s) => {
      s.addEventListener('click', () => { input.value = s.dataset.star; paint(Number(s.dataset.star)); });
    });
    paint(5);
  }
})();
(() => {
  const priceEl = document.getElementById('display-price');
  const priceNote = document.getElementById('price-qty-note');
  const radios = document.querySelectorAll('input[name="variant_id"]');
  const qtyInput = document.getElementById('qty');
  const waBtn = document.querySelector('[data-whatsapp-order]');
  const symbolMap = <?= json_encode(array_column(currency_rates(), 'symbol', 'code')) ?>;
  const rateMap = <?= json_encode(array_map('floatval', array_column(currency_rates(), 'rate_from_gbp', 'code'))) ?>;
  const currency = <?= json_encode(current_currency()) ?>;
  const format = (gbp) => {
    const amount = (gbp * (rateMap[currency] || 1)).toFixed(2);
    return (symbolMap[currency] || currency + ' ') + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const unitPriceGbp = () => {
    const selected = document.querySelector('input[name="variant_id"]:checked');
    if (selected) return parseFloat(selected.dataset.price || '0') || 0;
    return parseFloat(priceEl?.dataset.base || '0') || 0;
  };

  const quantity = () => Math.max(1, parseInt(qtyInput?.value || '1', 10) || 1);

  const syncPrice = () => {
    if (!priceEl) return;
    const unit = unitPriceGbp();
    const qty = quantity();
    const total = unit * qty;
    priceEl.dataset.base = String(unit);
    priceEl.textContent = format(total);
    if (priceNote) {
      if (qty > 1 && unit > 0) {
        priceNote.textContent = format(unit) + ' × ' + qty;
        priceNote.classList.remove('hidden');
      } else {
        priceNote.textContent = '';
        priceNote.classList.add('hidden');
      }
    }
  };

  const syncWhatsApp = () => {
    if (!waBtn) return;
    const selected = document.querySelector('input[name="variant_id"]:checked');
    const variant = selected ? (selected.dataset.label || '') : '';
    const unit = unitPriceGbp();
    const qty = quantity();
    const name = waBtn.dataset.productName || 'Product';
    const productUrl = waBtn.dataset.productUrl || window.location.href;
    const store = waBtn.dataset.storeName || 'By Claudia Darlene';
    const number = waBtn.dataset.waNumber || '';
    if (!number) return;

    const lines = [
      'Hi ' + store + '! I would like to order:',
      '',
      '• Product: ' + name,
    ];
    if (variant) lines.push('• Option: ' + variant);
    lines.push('• Quantity: ' + qty);
    if (unit > 0) {
      lines.push('• Unit price: ' + format(unit));
      if (qty > 1) lines.push('• Total: ' + format(unit * qty));
      else lines.push('• Price: ' + format(unit));
    }
    lines.push('', 'Link: ' + productUrl, '', 'Please confirm availability and how to pay. Thank you!');
    waBtn.href = 'https://wa.me/' + number + '?text=' + encodeURIComponent(lines.join('\n'));
  };

  const syncAll = () => {
    syncPrice();
    syncWhatsApp();
  };

  radios.forEach((r) => r.addEventListener('change', syncAll));
  if (qtyInput) {
    qtyInput.addEventListener('input', syncAll);
    qtyInput.addEventListener('change', () => {
      if (parseInt(qtyInput.value || '1', 10) < 1) qtyInput.value = '1';
      syncAll();
    });
  }
  syncAll();
})();

// Product gallery slider + cursor zoom
(() => {
  const gallery = document.querySelector('[data-gallery]');
  const mainImage = document.getElementById('product-main-image');
  const zoom = document.getElementById('product-zoom');
  if (!mainImage || !zoom) return;

  const slides = <?= json_encode(array_map(fn ($img) => asset($img), $thumbs)) ?>;
  const thumbs = Array.from(document.querySelectorAll('[data-thumb]'));
  const currentLabel = document.querySelector('[data-gallery-current]');
  const video = document.getElementById('product-main-video');
  const embedWrap = document.getElementById('product-main-embed');
  const embedFrame = embedWrap ? embedWrap.querySelector('iframe') : null;
  const videoThumb = document.querySelector('[data-video-thumb]');
  let idx = 0;
  let videoActive = false;

  const paintThumbs = () => thumbs.forEach((b) => {
    const on = Number(b.dataset.thumbIndex) === idx && !videoActive;
    b.classList.toggle('border-brand-ink', on);
    b.classList.toggle('border-brand-ink/10', !on);
  });

  const hideVideo = () => {
    if (video) {
      video.pause();
      video.classList.add('hidden');
    }
    if (embedWrap) {
      embedWrap.classList.add('hidden');
      if (embedFrame) embedFrame.src = '';
    }
    mainImage.classList.remove('hidden');
    zoom.classList.add('cursor-zoom-in');
    videoActive = false;
    if (videoThumb) videoThumb.classList.remove('border-brand-ink');
  };

  const show = (i) => {
    if (!slides.length) return;
    hideVideo();
    idx = (i + slides.length) % slides.length;
    mainImage.style.transform = '';
    mainImage.src = slides[idx];
    if (currentLabel) currentLabel.textContent = String(idx + 1);
    paintThumbs();
  };

  thumbs.forEach((b) => b.addEventListener('click', () => show(Number(b.dataset.thumbIndex))));
  const prev = document.querySelector('[data-gallery-prev]');
  const next = document.querySelector('[data-gallery-next]');
  if (prev) prev.addEventListener('click', () => show(idx - 1));
  if (next) next.addEventListener('click', () => show(idx + 1));

  if (videoThumb && (video || embedWrap)) {
    videoThumb.addEventListener('click', () => {
      mainImage.style.transform = '';
      mainImage.classList.add('hidden');
      zoom.classList.remove('cursor-zoom-in');
      videoActive = true;
      thumbs.forEach((b) => { b.classList.remove('border-brand-ink'); b.classList.add('border-brand-ink/10'); });
      videoThumb.classList.add('border-brand-ink');
      if (video) {
        video.classList.remove('hidden');
        video.play().catch(() => {});
      }
      if (embedWrap && embedFrame) {
        const src = embedFrame.getAttribute('data-embed-src') || '';
        embedWrap.classList.remove('hidden');
        if (src && embedFrame.src !== src) {
          embedFrame.src = src + (src.includes('?') ? '&' : '?') + 'autoplay=1';
        }
      }
    });
  }

  if (gallery && slides.length > 1) {
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') show(idx - 1);
      else if (e.key === 'ArrowRight') show(idx + 1);
    });
  }

  // Cursor-tracking zoom (pointer devices only, images only)
  const canZoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (canZoom) {
    const ZOOM = 2.4;
    zoom.addEventListener('mouseenter', () => { if (!videoActive) mainImage.style.transform = 'scale(' + ZOOM + ')'; });
    zoom.addEventListener('mousemove', (e) => {
      if (videoActive) return;
      const r = zoom.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width) * 100;
      const y = ((e.clientY - r.top) / r.height) * 100;
      mainImage.style.transformOrigin = x + '% ' + y + '%';
    });
    zoom.addEventListener('mouseleave', () => {
      mainImage.style.transform = '';
      mainImage.style.transformOrigin = 'center';
    });
  }
})();
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
