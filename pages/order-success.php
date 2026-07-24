<?php
declare(strict_types=1);

$pageTitle = 'Order Confirmed – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$orderNo = (string) get('order', $_SESSION['last_order'] ?? '');
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
$stmt->execute([$orderNo]);
$order = $stmt->fetch();

$orderItems = [];
$itemCount = 0;
$trackUrl = '';
$sym = '£';
$statusLabels = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
];

if ($order) {
    $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemsStmt->execute([(int) $order['id']]);
    $orderItems = $itemsStmt->fetchAll();
    foreach ($orderItems as $it) {
        $itemCount += (int) $it['quantity'];
    }
    $trackUrl = tracking_url($order['shipping_carrier'] ?? '', $order['tracking_number'] ?? '');
    $sym = currency_symbol((string) $order['currency']);
}

$user = current_user();
$firstName = '';
if ($order && !empty($order['shipping_name'])) {
    $firstName = explode(' ', trim((string) $order['shipping_name']))[0];
} elseif ($user) {
    $firstName = explode(' ', trim((string) $user['name']))[0];
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="success-page">
  <div class="success-page__inner">
    <div class="success-hero">
      <div class="success-hero__mark" aria-hidden="true">
        <span class="success-hero__ring"></span>
        <span class="success-hero__check">
          <svg width="34" height="34" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
        </span>
      </div>
      <p class="success-hero__eyebrow">Thank you<?= $firstName !== '' ? ', ' . e($firstName) : '' ?></p>
      <h1 class="success-hero__title font-display">Order confirmed</h1>
      <p class="success-hero__lead">
        <?php if ($order): ?>
          We’ve received your payment<?= !empty($order['payment_method']) ? ' via ' . e(ucfirst((string) $order['payment_method'])) : '' ?>.
          <?php if (!empty($order['email'])): ?>
            A confirmation is on its way to <strong><?= e((string) $order['email']) ?></strong>.
          <?php endif; ?>
        <?php else: ?>
          Your order was placed successfully. You’ll receive a confirmation shortly.
        <?php endif; ?>
      </p>
    </div>

    <?php if ($order): ?>
      <div class="success-summary">
        <div class="success-summary__top">
          <div>
            <p class="success-summary__label">Order number</p>
            <p class="success-summary__number"><?= e((string) $order['order_number']) ?></p>
          </div>
          <div class="success-summary__aside">
            <span class="success-summary__status"><?= e($statusLabels[(string) $order['status']] ?? ucfirst((string) $order['status'])) ?></span>
            <p class="success-summary__total font-display"><?= e($sym . number_format((float) $order['total'], 2)) ?></p>
          </div>
        </div>

        <div class="success-summary__meta">
          <div>
            <p class="success-summary__label">Date</p>
            <p><?= e(date('j M Y · g:ia', strtotime((string) $order['created_at']) ?: 'now')) ?></p>
          </div>
          <div>
            <p class="success-summary__label">Items</p>
            <p><?= (int) $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></p>
          </div>
          <div>
            <p class="success-summary__label">Payment</p>
            <p><?= e(ucfirst((string) ($order['payment_method'] ?: '—'))) ?></p>
          </div>
          <?php if (!empty($order['shipping_carrier'])): ?>
            <div>
              <p class="success-summary__label">Shipping</p>
              <p><?= e(carrier_label($order['shipping_carrier'])) ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="success-grid">
        <div class="success-panel">
          <h2 class="success-panel__title">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 6h16M4 12h16M4 18h10"/></svg>
            Order items
          </h2>
          <?php if (!$orderItems): ?>
            <p class="text-sm text-brand-soft">No line items recorded.</p>
          <?php else: ?>
            <ul class="success-items">
              <?php foreach ($orderItems as $item): ?>
                <li>
                  <div>
                    <p class="success-items__name"><?= e((string) $item['product_name']) ?></p>
                    <p class="success-items__meta">
                      <?php if (!empty($item['variant_label'])): ?>
                        <?= e((string) $item['variant_label']) ?> ·
                      <?php endif; ?>
                      Qty <?= (int) $item['quantity'] ?>
                    </p>
                  </div>
                  <span><?= e($sym . number_format((float) $item['line_total'], 2)) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="success-totals">
            <div><span>Subtotal</span><span><?= e($sym . number_format((float) $order['subtotal'], 2)) ?></span></div>
            <div><span>Shipping</span><span><?= e($sym . number_format((float) $order['shipping'], 2)) ?></span></div>
            <?php if ((float) $order['discount'] > 0): ?>
              <div><span>Discount</span><span>−<?= e($sym . number_format((float) $order['discount'], 2)) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($order['gift_card_amount']) && (float) $order['gift_card_amount'] > 0): ?>
              <div><span>Gift card</span><span>−<?= e($sym . number_format((float) $order['gift_card_amount'], 2)) ?></span></div>
            <?php endif; ?>
            <div class="success-totals__total"><span>Total</span><span><?= e($sym . number_format((float) $order['total'], 2)) ?></span></div>
          </div>
        </div>

        <div class="success-side">
          <div class="success-panel">
            <h2 class="success-panel__title">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              Shipping to
            </h2>
            <p class="success-address">
              <?php if (!empty($order['shipping_name'])): ?><?= e((string) $order['shipping_name']) ?><br><?php endif; ?>
              <?php if (!empty($order['shipping_address'])): ?><?= nl2br(e((string) $order['shipping_address'])) ?><br><?php endif; ?>
              <?php
                $cityLine = trim(implode(' ', array_filter([
                    (string) ($order['shipping_city'] ?? ''),
                    (string) ($order['shipping_postcode'] ?? ''),
                ])));
              ?>
              <?php if ($cityLine !== ''): ?><?= e($cityLine) ?><br><?php endif; ?>
              <?php if (!empty($order['shipping_country'])): ?><?= e((string) $order['shipping_country']) ?><?php endif; ?>
              <?php if (empty($order['shipping_name']) && empty($order['shipping_address'])): ?>
                <span class="text-brand-soft">No shipping address on file.</span>
              <?php endif; ?>
            </p>
            <?php if (!empty($order['shipping_carrier']) && $order['shipping_carrier'] !== 'standard' && !$trackUrl): ?>
              <p class="success-note">You’ll get a <?= e(carrier_label($order['shipping_carrier'])) ?> tracking link once it ships.</p>
            <?php endif; ?>
            <?php if ($trackUrl): ?>
              <a href="<?= e($trackUrl) ?>" target="_blank" rel="noopener" class="success-track">
                Track shipment
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              </a>
            <?php endif; ?>
          </div>

          <div class="success-panel success-panel--soft">
            <h2 class="success-panel__title">What’s next</h2>
            <ol class="success-steps">
              <li><span>1</span> We’re preparing your order in the studio</li>
              <li><span>2</span> You’ll get shipping updates by email<?= !empty($order['phone']) ? ' / SMS' : '' ?></li>
              <li><span>3</span> Track delivery once your parcel is on the way</li>
            </ol>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="success-actions">
      <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink success-actions__primary">Continue shopping</a>
      <?php if ($user): ?>
        <a href="<?= e(url('index.php?page=account&tab=orders')) ?>" class="success-actions__secondary">View my orders</a>
      <?php else: ?>
        <a href="<?= e(url('index.php?page=home')) ?>" class="success-actions__secondary">Back to home</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
