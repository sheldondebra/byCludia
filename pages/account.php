<?php
declare(strict_types=1);

require_login();
$user = current_user();
$pageTitle = 'My Account – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';

$orders = db()->prepare('SELECT * FROM orders WHERE user_id = ? OR email = ? ORDER BY id DESC LIMIT 20');
$orders->execute([$user['id'], $user['email']]);
$orderRows = $orders->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-4xl mx-auto px-6">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
      <div>
        <h1 class="font-display text-5xl mb-2">Hello, <?= e($user['name']) ?></h1>
        <p class="text-brand-soft">Loyalty points: <?= (int) $user['loyalty_points'] ?></p>
      </div>
      <div class="flex gap-3 text-sm">
        <a href="<?= e(url('index.php?page=wishlist')) ?>" class="underline">Wishlist</a>
        <a href="<?= e(url('index.php?page=logout')) ?>" class="underline">Sign out</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="<?= e(url('admin/index.php')) ?>" class="underline">Admin</a>
        <?php endif; ?>
      </div>
    </div>

    <h2 class="font-display text-3xl mb-5">Orders</h2>
    <?php if (!$orderRows): ?>
      <p class="text-brand-soft">No orders yet. <a class="underline" href="<?= e(url('index.php?page=shop')) ?>">Start shopping</a></p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($orderRows as $o): ?>
          <div class="bg-white/70 border border-brand-ink/5 rounded-2xl px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <p class="font-medium"><?= e($o['order_number']) ?></p>
              <p class="text-sm text-brand-soft"><?= e(ucfirst($o['status'])) ?> · <?= e($o['created_at']) ?></p>
              <?php $trackUrl = tracking_url($o['shipping_carrier'] ?? '', $o['tracking_number'] ?? ''); ?>
              <?php if ($trackUrl): ?>
                <a href="<?= e($trackUrl) ?>" target="_blank" rel="noopener" class="text-sm text-brand-ink underline mt-1 inline-block">Track <?= e(carrier_label($o['shipping_carrier'] ?? '')) ?> · <?= e((string) $o['tracking_number']) ?></a>
              <?php elseif (!empty($o['tracking_number'])): ?>
                <p class="text-sm text-brand-soft mt-1">Tracking: <?= e((string) $o['tracking_number']) ?></p>
              <?php endif; ?>
            </div>
            <p class="font-medium"><?= e(currency_symbol($o['currency']) . number_format((float) $o['total'], 2)) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
