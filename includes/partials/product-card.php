<?php
declare(strict_types=1);
/** @var array $product */
$minPrice = (float) $product['base_price'];

$cardVariantStmt = db()->prepare('SELECT id, price FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY price ASC LIMIT 1');
$cardVariantStmt->execute([(int) $product['id']]);
$cardVariant = $cardVariantStmt->fetch();
$cardVariantId = (int) ($cardVariant['id'] ?? 0);

$isFav = false;
if ($cardUser = current_user()) {
    $favStmt = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
    $favStmt->execute([(int) $cardUser['id'], (int) $product['id']]);
    $isFav = (bool) $favStmt->fetchColumn();
}
$productUrl = url('product/' . $product['slug']);
$cardAlt = seo_product_alt($product);

$cardSlides = [];
if (!empty($product['image']) && file_exists(ROOT_PATH . '/' . $product['image'])) {
    $cardSlides[] = $product['image'];
}
if (!empty($product['gallery'])) {
    $decodedGallery = json_decode((string) $product['gallery'], true);
    if (is_array($decodedGallery)) {
        foreach ($decodedGallery as $img) {
            $img = (string) $img;
            if ($img !== '' && file_exists(ROOT_PATH . '/' . $img) && !in_array($img, $cardSlides, true)) {
                $cardSlides[] = $img;
            }
        }
    }
}
$slideCount = count($cardSlides);
$canSlide = $slideCount > 1;
?>
<div class="product-card reveal group relative block" <?= $canSlide ? 'data-card-slider' : '' ?>>
  <button type="button"
    data-wishlist-toggle="<?= (int) $product['id'] ?>"
    aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
    aria-label="Add to favourites"
    title="Add to favourites"
    class="absolute top-3 left-3 z-20 w-9 h-9 rounded-full bg-white/90 backdrop-blur flex items-center justify-center shadow-soft/50 hover:bg-white transition <?= $isFav ? 'text-rose-500' : 'text-brand-ink' ?>">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="<?= $isFav ? 'currentColor' : 'none' ?>" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21C12 21 4 13.9 4 8.8 4 6.1 6.1 4 8.8 4c1.6 0 3.1.8 3.2 2 .1-1.2 1.6-2 3.2-2C17.9 4 20 6.1 20 8.8c0 5.1-8 12.2-8 12.2z"/></svg>
  </button>
  <button type="button"
    data-compare-toggle="<?= (int) $product['id'] ?>"
    aria-label="Add to compare"
    title="Add to compare"
    class="absolute top-3 right-3 z-20 w-9 h-9 rounded-full bg-white/90 backdrop-blur flex items-center justify-center shadow-soft/50 hover:bg-white transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h2m6-16h2a2 2 0 012 2v12a2 2 0 01-2 2h-2m-3-18v20"/></svg>
  </button>

  <div class="relative aspect-[4/5] rounded-2xl overflow-hidden bg-white mb-4 shadow-soft/50">
    <?php if (!empty($product['on_sale'])): ?>
      <span class="absolute top-3 left-14 z-10 text-[10px] tracking-[0.14em] uppercase bg-brand-ink text-white px-2.5 py-1 rounded-full">Sale</span>
    <?php endif; ?>
    <a href="<?= e($productUrl) ?>" class="product-card__media block w-full h-full relative">
      <?php if ($slideCount > 0): ?>
        <div class="product-card__slides absolute inset-0" data-slide-track>
          <?php foreach ($cardSlides as $i => $slide): ?>
            <img
              src="<?= e(asset($slide)) ?>"
              alt="<?= e($cardAlt) ?><?= $i > 0 ? ' view ' . ($i + 1) : '' ?>"
              class="product-card__slide absolute inset-0 w-full h-full object-cover <?= $i === 0 ? 'is-active' : '' ?>"
              data-slide-index="<?= $i ?>"
              <?= $i > 0 ? 'loading="lazy"' : '' ?>
            >
          <?php endforeach; ?>
        </div>
        <?php if ($canSlide): ?>
          <div class="product-card__dots absolute bottom-14 left-0 right-0 z-10 flex justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none" aria-hidden="true">
            <?php for ($i = 0; $i < $slideCount; $i++): ?>
              <span class="product-card__dot <?= $i === 0 ? 'is-active' : '' ?>" data-dot-index="<?= $i ?>"></span>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="product-placeholder relative w-full h-full flex items-end p-5 overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-br from-[#2c2420] via-[#6a4a3a] to-brand-blush"></div>
          <div class="absolute inset-0 opacity-40" style="background-image:radial-gradient(circle at 70% 20%, rgba(255,255,255,.35), transparent 40%), radial-gradient(circle at 20% 80%, rgba(243,196,196,.5), transparent 45%);"></div>
          <span class="relative font-display text-xl text-white/90 leading-tight drop-shadow"><?= e(explode('–', $product['name'])[0]) ?></span>
        </div>
      <?php endif; ?>
    </a>

    <div class="absolute inset-x-0 bottom-0 p-3 flex flex-col gap-2 translate-y-3 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition duration-300 z-10">
      <?php if ($cardVariantId > 0): ?>
        <button type="button" data-quick-add="<?= (int) $product['id'] ?>" data-variant="<?= $cardVariantId ?>"
          class="w-full rounded-full bg-white/95 backdrop-blur text-brand-ink py-2.5 text-xs tracking-[0.12em] uppercase font-medium hover:bg-white transition flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Add to Cart</button>
        <button type="button" data-buy-now="<?= (int) $product['id'] ?>" data-variant="<?= $cardVariantId ?>"
          class="w-full rounded-full bg-brand-ink text-white py-2.5 text-xs tracking-[0.12em] uppercase font-medium hover:opacity-90 transition flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6zM3 6h18M16 10a4 4 0 01-8 0"/></svg>
          Buy Now</button>
      <?php else: ?>
        <a href="<?= e($productUrl) ?>" class="w-full text-center rounded-full bg-brand-ink text-white py-2.5 text-xs tracking-[0.12em] uppercase font-medium">View</a>
      <?php endif; ?>
    </div>
  </div>

  <a href="<?= e($productUrl) ?>" class="block">
    <h3 class="font-display text-lg sm:text-xl leading-snug mb-1 group-hover:opacity-70 transition"><?= e($product['name']) ?></h3>
    <div class="text-sm mb-1"><?= stars((float) $product['rating']) ?></div>
    <p class="text-sm font-medium"><?= money($minPrice) ?><?= !empty($product['compare_at_price']) ? ' <span class="text-brand-soft line-through ml-1">' . money((float) $product['compare_at_price']) . '</span>' : '' ?></p>
  </a>
</div>
