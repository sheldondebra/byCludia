<?php
declare(strict_types=1);

$pageTitle = 'Compare – Hair by Claudia Darlene';
$pageDescription = 'Compare hair textures, prices, and details side by side.';
$robots = 'noindex, follow';

$ids = [];
foreach (explode(',', (string) get('ids', '')) as $raw) {
    $id = (int) trim($raw);
    if ($id > 0) {
        $ids[$id] = $id;
    }
}
$ids = array_slice(array_values($ids), 0, 4);

$products = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.id IN (' . $placeholders . ') AND p.is_active = 1'
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $products[] = $byId[$id];
        }
    }
}

$variantByProduct = [];
foreach ($products as $p) {
    $vStmt = db()->prepare(
        'SELECT id, label, price FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY price ASC, id ASC LIMIT 1'
    );
    $vStmt->execute([(int) $p['id']]);
    $variantByProduct[(int) $p['id']] = $vStmt->fetch() ?: null;
}

$waNumber = whatsapp_number();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20" data-compare-page>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="font-display text-5xl text-center mb-4">Compare</h1>
    <p class="text-center text-brand-soft mb-10 max-w-xl mx-auto">Line up your favourites side by side. Add items using the compare icon on any product.</p>

    <div data-compare-empty class="<?= $products ? 'hidden' : '' ?> text-center bg-white/70 rounded-3xl border border-brand-ink/5 p-10">
      <p class="text-brand-soft mb-4">You haven&rsquo;t added anything to compare yet.</p>
      <a href="<?= e(url('shop')) ?>" class="inline-block rounded-full bg-brand-ink text-white px-6 py-3 text-sm tracking-[0.14em] uppercase">Browse shop</a>
    </div>

    <?php if ($products): ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] border-separate border-spacing-x-4">
          <tbody>
            <tr>
              <th class="w-28 align-bottom text-left text-xs tracking-[0.16em] uppercase text-brand-soft"></th>
              <?php foreach ($products as $p): ?>
                <td class="align-top w-64">
                  <div class="bg-white/70 border border-brand-ink/5 rounded-3xl overflow-hidden">
                    <a href="<?= e(url('product/' . $p['slug'])) ?>">
                      <?php if (!empty($p['image']) && file_exists(ROOT_PATH . '/' . $p['image'])): ?>
                        <div class="aspect-[4/5] overflow-hidden"><img src="<?= e(asset((string) $p['image'])) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover"></div>
                      <?php else: ?>
                        <div class="aspect-[4/5] bg-gradient-to-br from-brand-mist via-brand-blush/50 to-[#e8c4a8] flex items-end p-4"><span class="font-display text-lg text-brand-ink/70 leading-tight"><?= e(explode('–', $p['name'])[0]) ?></span></div>
                      <?php endif; ?>
                    </a>
                    <div class="p-3 text-center">
                      <button type="button" data-compare-remove="<?= (int) $p['id'] ?>" class="text-xs underline text-brand-soft hover:text-brand-ink">Remove</button>
                    </div>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Name</th>
              <?php foreach ($products as $p): ?><td class="py-3 font-display text-lg leading-snug"><?= e($p['name']) ?></td><?php endforeach; ?>
            </tr>
            <tr class="border-t">
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Price</th>
              <?php foreach ($products as $p):
                  $variant = $variantByProduct[(int) $p['id']] ?? null;
                  $price = (float) ($variant['price'] ?? $p['base_price']);
              ?>
                <td class="py-3 font-medium">
                  <?= money($price) ?>
                  <?= !empty($p['compare_at_price']) ? '<span class="text-brand-soft line-through ml-1 text-sm">' . money((float) $p['compare_at_price']) . '</span>' : '' ?>
                  <?php if (!empty($variant['label'])): ?>
                    <span class="block text-xs text-brand-soft font-normal mt-0.5"><?= e((string) $variant['label']) ?></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Category</th>
              <?php foreach ($products as $p): ?><td class="py-3 text-sm"><?= e($p['category_name'] ?? '—') ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Rating</th>
              <?php foreach ($products as $p): ?><td class="py-3"><?= stars((float) $p['rating']) ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3 align-top">Details</th>
              <?php foreach ($products as $p): ?><td class="py-3 text-sm text-brand-soft"><?= e($p['short_description'] ?? '') ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3 align-top">Actions</th>
              <?php foreach ($products as $p):
                  $pid = (int) $p['id'];
                  $variant = $variantByProduct[$pid] ?? null;
                  $variantId = (int) ($variant['id'] ?? 0);
                  $productUrl = url('product/' . $p['slug']);
                  $waBuy = ($waNumber !== '' && $variant)
                      ? whatsapp_order_url([
                          'name' => $p['name'],
                          'variant' => (string) ($variant['label'] ?? ''),
                          'quantity' => 1,
                          'price' => money((float) $variant['price']),
                          'url' => $productUrl,
                      ])
                      : ($waNumber !== '' ? whatsapp_order_url([
                          'name' => $p['name'],
                          'quantity' => 1,
                          'price' => money((float) $p['base_price']),
                          'url' => $productUrl,
                      ]) : '');
                  $waAsk = $waNumber !== '' ? whatsapp_question_url([
                      'name' => $p['name'],
                      'url' => $productUrl,
                  ]) : '';
              ?>
                <td class="py-3 align-top">
                  <div class="flex flex-col gap-2 max-w-[14rem]">
                    <?php if ($variantId > 0): ?>
                      <button type="button"
                        data-quick-add="<?= $pid ?>"
                        data-variant="<?= $variantId ?>"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full bg-brand-ink text-white px-4 py-2.5 text-[11px] tracking-[0.12em] uppercase font-medium hover:opacity-90 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
                        <span data-btn-label>Add to cart</span>
                      </button>
                      <button type="button"
                        data-buy-now="<?= $pid ?>"
                        data-variant="<?= $variantId ?>"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full bg-brand-blush text-brand-ink px-4 py-2.5 text-[11px] tracking-[0.12em] uppercase font-medium hover:bg-brand-blushDeep transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span data-btn-label>Buy now</span>
                      </button>
                    <?php else: ?>
                      <a href="<?= e($productUrl) ?>" class="inline-flex items-center justify-center rounded-full bg-brand-ink text-white px-4 py-2.5 text-[11px] tracking-[0.12em] uppercase font-medium">View options</a>
                    <?php endif; ?>

                    <?php if ($waBuy !== ''): ?>
                      <a href="<?= e($waBuy) ?>" target="_blank" rel="noopener"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full bg-[#25D366] text-white px-4 py-2.5 text-[11px] tracking-[0.12em] uppercase font-medium hover:bg-[#1ebe57] transition">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
                        WhatsApp buy
                      </a>
                    <?php endif; ?>

                    <?php if ($waAsk !== ''): ?>
                      <a href="<?= e($waAsk) ?>" target="_blank" rel="noopener"
                        class="inline-flex items-center justify-center gap-1.5 rounded-full border border-brand-ink/15 bg-white px-4 py-2.5 text-[11px] tracking-[0.12em] uppercase font-medium text-brand-ink hover:bg-brand-mist/60 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ask a question
                      </a>
                    <?php endif; ?>

                    <a href="<?= e($productUrl) ?>" class="text-center text-[11px] underline text-brand-soft hover:text-brand-ink pt-0.5">View details</a>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
