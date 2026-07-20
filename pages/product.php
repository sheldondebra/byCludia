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
$ogType = 'product';
if ($mainImage) {
    $ogImage = $mainImage;
}
$lowestPrice = $defaultVariant['price'] ?? ($product['price'] ?? 0);
$inStock = false;
foreach ($variants as $v) {
    if ((int) $v['stock'] > 0) { $inStock = true; break; }
}
$productLd = [
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => strip_tags((string) ($product['short_description'] ?? $product['description'] ?? $product['name'])),
    'sku' => (string) ($defaultVariant['sku'] ?? ('CD-' . $product['id'])),
    'image' => array_map(fn ($img) => asset($img), $thumbs ?: [$logoPath ?? 'assets/images/logo.png']),
    'brand' => ['@type' => 'Brand', 'name' => setting('store_name', 'By Claudia Darlene')],
    'offers' => [
        '@type' => 'Offer',
        'url' => $canonical,
        'priceCurrency' => 'GBP',
        'price' => number_format((float) $lowestPrice, 2, '.', ''),
        'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
];
if ((int) ($product['review_count'] ?? 0) > 0) {
    $productLd['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => (string) $product['rating'],
        'reviewCount' => (string) $product['review_count'],
    ];
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $productLd,
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => url('shop')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $product['name'], 'item' => $canonical],
            ],
        ],
    ],
];

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-12 sm:py-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-16">
    <div class="reveal">
      <?php if ($mainImage): ?>
        <div class="relative group" data-gallery>
          <div id="product-zoom" class="aspect-[4/5] rounded-[28px] overflow-hidden bg-white shadow-soft cursor-zoom-in">
            <img id="product-main-image" src="<?= e(asset($mainImage)) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-cover transition-transform duration-150 ease-out will-change-transform">
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
      <p id="display-price" class="text-2xl font-medium mb-6" data-base="<?= e((string) ($defaultVariant['price'] ?? $product['base_price'])) ?>">
        <?= money((float) ($defaultVariant['price'] ?? $product['base_price'])) ?>
      </p>

      <?php if ($variants): ?>
        <form data-add-to-cart method="post" class="space-y-6">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
          <div>
            <label class="block text-xs tracking-[0.18em] uppercase text-brand-soft mb-3">Length</label>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($variants as $i => $v): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" data-price="<?= e((string) $v['price']) ?>" class="peer sr-only" <?= $i === 0 ? 'checked' : '' ?> required>
                  <span class="inline-block px-4 py-2 rounded-full border border-brand-ink/15 text-sm peer-checked:bg-brand-ink peer-checked:text-white peer-checked:border-brand-ink transition"><?= e($v['label']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label for="qty" class="block text-xs tracking-[0.18em] uppercase text-brand-soft mb-3">Quantity</label>
            <input id="qty" type="number" name="quantity" min="1" value="1" class="w-24 rounded-full border border-brand-ink/15 bg-white px-4 py-2.5 text-sm">
          </div>
          <button type="submit" class="btn-ink px-10 py-3.5 text-sm tracking-[0.12em] uppercase w-full sm:w-auto">Add to Cart</button>
        </form>
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
  const radios = document.querySelectorAll('input[name="variant_id"]');
  const symbolMap = <?= json_encode(array_column(currency_rates(), 'symbol', 'code')) ?>;
  const rateMap = <?= json_encode(array_map('floatval', array_column(currency_rates(), 'rate_from_gbp', 'code'))) ?>;
  const currency = <?= json_encode(current_currency()) ?>;
  const format = (gbp) => {
    const amount = (gbp * (rateMap[currency] || 1)).toFixed(2);
    return (symbolMap[currency] || currency + ' ') + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };
  radios.forEach((r) => r.addEventListener('change', () => {
    if (priceEl) priceEl.textContent = format(parseFloat(r.dataset.price));
  }));

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
