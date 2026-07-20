<?php
declare(strict_types=1);

$pageTitle = 'Order Confirmed – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$orderNo = (string) get('order', $_SESSION['last_order'] ?? '');
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
$stmt->execute([$orderNo]);
$order = $stmt->fetch();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-20">
  <div class="max-w-xl mx-auto px-6 text-center">
    <p class="text-xs tracking-[0.28em] uppercase text-brand-soft mb-3">Thank you</p>
    <h1 class="font-display text-5xl mb-4">Order confirmed</h1>
    <?php if ($order): ?>
      <p class="text-brand-soft mb-2">Order <strong class="text-brand-ink"><?= e($order['order_number']) ?></strong></p>
      <p class="text-brand-soft mb-8">We've received your payment via <?= e(ucfirst((string) $order['payment_method'])) ?>. A confirmation will be sent to <?= e($order['email']) ?>.</p>
      <?php if (!empty($order['shipping_carrier']) && $order['shipping_carrier'] !== 'standard'): ?>
        <p class="text-brand-soft mb-8 -mt-6">Shipping via <strong class="text-brand-ink"><?= e(carrier_label($order['shipping_carrier'])) ?></strong> — you'll get a tracking link once it ships.</p>
      <?php endif; ?>
      <p class="font-display text-3xl mb-10"><?= e(currency_symbol($order['currency']) . number_format((float) $order['total'], 2)) ?></p>
    <?php else: ?>
      <p class="text-brand-soft mb-8">Your order was placed successfully.</p>
    <?php endif; ?>
    <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-block px-8 py-3 text-sm tracking-[0.12em] uppercase">Continue Shopping</a>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
