<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$q = trim((string) get('q', ''));
$orders = $products = $customers = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $s = db()->prepare('SELECT id, order_number, email, status, total, currency FROM orders WHERE order_number LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 20');
    $s->execute([$like, $like]);
    $orders = $s->fetchAll();

    $s = db()->prepare('SELECT id, name, base_price, is_active FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 20');
    $s->execute([$like]);
    $products = $s->fetchAll();

    $s = db()->prepare("SELECT id, name, email FROM users WHERE role = 'customer' AND (name LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 20");
    $s->execute([$like, $like]);
    $customers = $s->fetchAll();
}

$total = count($orders) + count($products) + count($customers);

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-2">Search</h1>
<p class="text-stone-500 mb-8">
  <?php if ($q === ''): ?>Type a query in the search bar above.<?php else: ?><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for &ldquo;<?= e($q) ?>&rdquo;<?php endif; ?>
</p>

<?php if ($q !== ''): ?>
<div class="space-y-8">
  <section>
    <h2 class="font-display text-2xl mb-3 flex items-center gap-2"><?= admin_icon('receipt-text') ?> Orders</h2>
    <div class="bg-white rounded-2xl border border-stone-200 divide-y divide-stone-100">
      <?php foreach ($orders as $o): ?>
        <a href="order.php?id=<?= (int) $o['id'] ?>" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-stone-50">
          <span><?= e($o['order_number']) ?> · <?= e($o['email']) ?></span>
          <span class="flex items-center gap-3"><?= admin_status_badge((string) $o['status']) ?> <?= e($o['currency'] . ' ' . number_format((float) $o['total'], 2)) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$orders): ?><p class="px-4 py-4 text-sm text-stone-400">No matching orders</p><?php endif; ?>
    </div>
  </section>

  <section>
    <h2 class="font-display text-2xl mb-3 flex items-center gap-2"><?= admin_icon('package') ?> Products</h2>
    <div class="bg-white rounded-2xl border border-stone-200 divide-y divide-stone-100">
      <?php foreach ($products as $p): ?>
        <a href="product-edit.php?id=<?= (int) $p['id'] ?>" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-stone-50">
          <span><?= e($p['name']) ?></span>
          <span class="flex items-center gap-3">&pound;<?= number_format((float) $p['base_price'], 2) ?> <?= $p['is_active'] ? '' : '<span class="text-xs text-stone-400">(hidden)</span>' ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$products): ?><p class="px-4 py-4 text-sm text-stone-400">No matching products</p><?php endif; ?>
    </div>
  </section>

  <section>
    <h2 class="font-display text-2xl mb-3 flex items-center gap-2"><?= admin_icon('users') ?> Customers</h2>
    <div class="bg-white rounded-2xl border border-stone-200 divide-y divide-stone-100">
      <?php foreach ($customers as $c): ?>
        <a href="orders.php?q=<?= urlencode((string) $c['email']) ?>" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-stone-50">
          <span><?= e($c['name']) ?></span>
          <span class="text-stone-500"><?= e($c['email']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$customers): ?><p class="px-4 py-4 text-sm text-stone-400">No matching customers</p><?php endif; ?>
    </div>
  </section>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
