<?php
declare(strict_types=1);
/** @var array $items */
/** @var string $subtotal */
/** @var string $cartUrl */
/** @var string $shopUrl */
?>
<?php if (!$items): ?>
  <div class="cart-nav__empty px-5 py-8 text-center">
    <p class="font-display text-xl mb-1.5 text-brand-ink">Your bag is empty</p>
    <p class="text-sm text-brand-soft mb-5">Textures made to blend, move, and feel like you.</p>
    <a href="<?= e($shopUrl) ?>" class="inline-block text-xs tracking-[0.14em] uppercase underline underline-offset-4 decoration-brand-ink/25 hover:decoration-brand-ink transition">Shop the collection</a>
  </div>
<?php else: ?>
  <ul class="cart-nav__list max-h-72 overflow-y-auto divide-y divide-brand-ink/10">
    <?php foreach ($items as $item): ?>
      <?php
        $isGift = cart_item_is_gift($item);
        $qty = (int) $item['quantity'];
        $lineTotal = (float) $item['unit_price'] * $qty;
        $img = (!$isGift && !empty($item['image']) && file_exists(ROOT_PATH . '/' . $item['image']))
          ? $item['image']
          : null;
        $productUrl = $isGift
          ? url('index.php?page=gift-cards')
          : url('index.php?page=product&slug=' . urlencode((string) $item['slug']));
        $label = $isGift ? 'Gift Card' : (string) $item['name'];
        $meta = $isGift
          ? ('To ' . (string) ($item['gift_recipient_name'] ?: $item['gift_recipient_email']))
          : (string) $item['variant_label'];
      ?>
      <li class="cart-nav__item flex gap-3 px-4 py-3.5">
        <a href="<?= e($productUrl) ?>" class="cart-nav__thumb shrink-0 w-14 h-[4.5rem] rounded-lg overflow-hidden bg-brand-mist">
          <?php if ($isGift): ?>
            <span class="flex w-full h-full items-center justify-center bg-gradient-to-br from-brand-ink via-[#3a2f2c] to-[#6a4a3a] text-brand-blush">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M20 12v9H4v-9M2 7h20v5H2zM12 7v14M12 7c-1.5-3-6-3-6 0h6zm0 0c1.5-3 6-3 6 0h-6z"/></svg>
            </span>
          <?php elseif ($img): ?>
            <img src="<?= e(asset($img)) ?>" alt="" class="w-full h-full object-cover">
          <?php else: ?>
            <span class="block w-full h-full bg-gradient-to-br from-brand-mist via-brand-blush/40 to-[#e8c4a8]"></span>
          <?php endif; ?>
        </a>
        <div class="min-w-0 flex-1">
          <a href="<?= e($productUrl) ?>" class="block font-display text-[1.05rem] leading-snug text-brand-ink hover:opacity-70 transition truncate"><?= e($label) ?></a>
          <?php if ($meta !== ''): ?>
            <p class="text-xs text-brand-soft mt-0.5 truncate"><?= e($meta) ?></p>
          <?php endif; ?>
          <p class="text-xs text-brand-soft mt-1.5 tabular-nums">
            Qty <?= $qty ?> · <?= e(money($lineTotal)) ?>
          </p>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="cart-nav__footer border-t border-brand-ink/10 px-4 py-4 bg-brand-mist/40">
    <div class="flex items-center justify-between text-sm mb-3.5">
      <span class="text-brand-soft">Subtotal</span>
      <span class="font-medium tabular-nums"><?= e($subtotal) ?></span>
    </div>
    <a href="<?= e($cartUrl) ?>" class="btn-ink block w-full text-center px-4 py-2.5 text-xs tracking-[0.14em] uppercase">View cart</a>
  </div>
<?php endif; ?>
