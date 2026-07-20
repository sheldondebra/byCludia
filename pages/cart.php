<?php
declare(strict_types=1);

$pageTitle = 'Your Cart – Hair by Claudia Darlene';
$pageDescription = 'Review your Hair by Claudia Darlene bag before checkout.';
$robots = 'noindex, follow';
$items = cart_items();
$subtotal = cart_subtotal_gbp();
$itemCount = (int) array_sum(array_map(static fn ($i) => (int) $i['quantity'], $items));
$shippingNote = (float) (setting('shipping_flat', '15') ?: 15);
$freeThreshold = (float) (setting('free_shipping_threshold', '') ?: 0);

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        flash('error', 'Invalid session. Please try again.');
        redirect('index.php?page=cart');
    }
    $action = post('action');
    if ($action === 'update') {
        cart_update((int) post('item_id'), (int) post('quantity'));
        flash('success', 'Bag updated.');
    } elseif ($action === 'remove') {
        cart_remove((int) post('item_id'));
        flash('success', 'Item removed from your bag.');
    }
    redirect('index.php?page=cart');
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="cart-page py-12 sm:py-16 lg:py-20">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-10 sm:mb-12">
      <div>
        <p class="text-[11px] tracking-[0.28em] uppercase text-brand-soft mb-2">Your bag</p>
        <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl leading-none">Shopping Cart</h1>
      </div>
      <?php if ($items): ?>
        <p class="text-sm text-brand-soft">
          <?= $itemCount ?> <?= $itemCount === 1 ? 'item' : 'items' ?>
          · <a href="<?= e(url('index.php?page=shop')) ?>" class="underline underline-offset-4 decoration-brand-ink/20 hover:decoration-brand-ink transition">Continue shopping</a>
        </p>
      <?php endif; ?>
    </div>

    <?php if (!$items): ?>
      <div class="cart-empty text-center py-20 sm:py-28 px-6">
        <p class="font-display text-3xl sm:text-4xl mb-3">Your bag is empty</p>
        <p class="text-brand-soft mb-8 max-w-md mx-auto">Discover textures made to blend, move, and feel like you.</p>
        <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-block px-9 py-3.5 text-sm tracking-[0.14em] uppercase">Shop the Collection</a>
      </div>
    <?php else: ?>
      <div class="grid lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.85fr)] gap-10 lg:gap-14 items-start">
        <div class="cart-lines">
          <div class="hidden sm:grid grid-cols-[1fr_auto_auto] gap-6 text-[11px] tracking-[0.18em] uppercase text-brand-soft/80 pb-3 border-b border-brand-ink/10">
            <span>Product</span>
            <span class="w-28 text-center">Qty</span>
            <span class="w-24 text-right">Total</span>
          </div>

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
            ?>
            <article class="cart-line py-6 sm:py-7 border-b border-brand-ink/10">
              <div class="grid grid-cols-[88px_1fr] sm:grid-cols-[104px_1fr_auto_auto] gap-4 sm:gap-6 items-start sm:items-center">
                <a href="<?= e($productUrl) ?>" class="cart-line__media block aspect-[4/5] rounded-xl overflow-hidden bg-brand-mist shrink-0">
                  <?php if ($isGift): ?>
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-ink via-[#3a2f2c] to-[#6a4a3a] text-brand-blush">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M20 12v9H4v-9M2 7h20v5H2zM12 7v14M12 7c-1.5-3-6-3-6 0h6zm0 0c1.5-3 6-3 6 0h-6z"/></svg>
                    </div>
                  <?php elseif ($img): ?>
                    <img src="<?= e(asset($img)) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover">
                  <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-br from-brand-mist via-brand-blush/40 to-[#e8c4a8]"></div>
                  <?php endif; ?>
                </a>

                <div class="min-w-0">
                  <a href="<?= e($productUrl) ?>" class="font-display text-xl sm:text-2xl leading-snug hover:opacity-70 transition block">
                    <?= $isGift ? 'Gift Card' : e($item['name']) ?>
                  </a>
                  <?php if ($isGift): ?>
                    <p class="text-sm text-brand-soft mt-1.5">
                      To <?= e((string) ($item['gift_recipient_name'] ?: $item['gift_recipient_email'])) ?>
                    </p>
                    <p class="text-xs text-brand-soft/80 mt-0.5"><?= e((string) $item['gift_recipient_email']) ?></p>
                  <?php else: ?>
                    <p class="text-sm text-brand-soft mt-1.5"><?= e((string) $item['variant_label']) ?></p>
                  <?php endif; ?>
                  <p class="text-sm mt-2 sm:hidden"><?= money((float) $item['unit_price']) ?> each</p>

                  <div class="flex items-center gap-4 mt-4 sm:hidden">
                    <?php if (!$isGift): ?>
                      <form method="post" class="cart-qty">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" name="quantity" value="<?= max(1, $qty - 1) ?>" class="cart-qty__btn" aria-label="Decrease quantity" <?= $qty <= 1 ? 'disabled' : '' ?>>−</button>
                        <span class="cart-qty__val"><?= $qty ?></span>
                        <button type="submit" name="quantity" value="<?= min((int) $item['stock'], $qty + 1) ?>" class="cart-qty__btn" aria-label="Increase quantity" <?= $qty >= (int) $item['stock'] ? 'disabled' : '' ?>>+</button>
                      </form>
                    <?php else: ?>
                      <span class="text-sm text-brand-soft">Qty 1</span>
                    <?php endif; ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                      <button type="submit" class="text-xs tracking-[0.12em] uppercase text-brand-soft hover:text-rose-600 transition">Remove</button>
                    </form>
                  </div>
                </div>

                <div class="hidden sm:flex flex-col items-center gap-3 w-28">
                  <?php if (!$isGift): ?>
                    <form method="post" class="cart-qty">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                      <button type="submit" name="quantity" value="<?= max(1, $qty - 1) ?>" class="cart-qty__btn" aria-label="Decrease quantity" <?= $qty <= 1 ? 'disabled' : '' ?>>−</button>
                      <span class="cart-qty__val"><?= $qty ?></span>
                      <button type="submit" name="quantity" value="<?= min((int) $item['stock'], $qty + 1) ?>" class="cart-qty__btn" aria-label="Increase quantity" <?= $qty >= (int) $item['stock'] ? 'disabled' : '' ?>>+</button>
                    </form>
                  <?php else: ?>
                    <span class="text-sm text-brand-soft">1</span>
                  <?php endif; ?>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="text-[11px] tracking-[0.14em] uppercase text-brand-soft hover:text-rose-600 transition">Remove</button>
                  </form>
                </div>

                <div class="hidden sm:block w-24 text-right">
                  <p class="font-medium"><?= money($lineTotal) ?></p>
                  <?php if ($qty > 1): ?>
                    <p class="text-xs text-brand-soft mt-1"><?= money((float) $item['unit_price']) ?> each</p>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <aside class="cart-summary lg:sticky lg:top-28">
          <div class="cart-summary__panel p-6 sm:p-7">
            <h2 class="font-display text-2xl mb-6">Order summary</h2>
            <div class="space-y-3 text-sm border-b border-brand-ink/10 pb-5 mb-5">
              <div class="flex justify-between gap-4">
                <span class="text-brand-soft">Subtotal</span>
                <span class="font-medium"><?= money($subtotal) ?></span>
              </div>
              <div class="flex justify-between gap-4">
                <span class="text-brand-soft">Shipping</span>
                <span class="text-brand-soft text-right">
                  <?php if ($freeThreshold > 0 && $subtotal >= $freeThreshold): ?>
                    <span class="text-emerald-700 font-medium">Free</span>
                  <?php else: ?>
                    Calculated at checkout
                    <?php if ($shippingNote > 0): ?>
                      <span class="block text-xs mt-0.5">from <?= money($shippingNote) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </span>
              </div>
            </div>
            <div class="flex justify-between items-baseline gap-4 mb-6">
              <span class="text-sm tracking-[0.12em] uppercase text-brand-soft">Total</span>
              <span class="font-display text-3xl"><?= money($subtotal) ?></span>
            </div>
            <p class="text-xs text-brand-soft mb-5">Taxes &amp; shipping confirmed at checkout. Paying in <?= e(current_currency()) ?>.</p>
            <a href="<?= e(url('index.php?page=checkout')) ?>" class="btn-ink block text-center w-full py-3.5 text-sm tracking-[0.14em] uppercase mb-3">
              Checkout
            </a>
            <a href="<?= e(url('index.php?page=shop')) ?>" class="block text-center text-sm text-brand-soft hover:text-brand-ink transition underline underline-offset-4 decoration-brand-ink/15">
              Continue shopping
            </a>
          </div>
          <p class="mt-4 text-center text-[11px] tracking-[0.16em] uppercase text-brand-soft/70">
            Secure checkout · Worldwide shipping
          </p>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
