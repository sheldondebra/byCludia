<?php
declare(strict_types=1);

require_login();
$user = current_user();
$pageTitle = 'My Account – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';

$orders = db()->prepare(
    'SELECT * FROM orders
     WHERE user_id = ?
        OR (email IS NOT NULL AND email != "" AND email = ?)
        OR (phone IS NOT NULL AND phone != "" AND phone = ?)
     ORDER BY id DESC
     LIMIT 30'
);
$orders->execute([
    (int) $user['id'],
    (string) ($user['email'] ?? ''),
    (string) ($user['phone'] ?? ''),
]);
$orderRows = $orders->fetchAll();

$orderItemsById = [];
if ($orderRows) {
    $ids = array_map(static fn ($o) => (int) $o['id'], $orderRows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() as $item) {
        $oid = (int) $item['order_id'];
        $orderItemsById[$oid][] = $item;
    }
}

$wishlistCount = 0;
$wlStmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
$wlStmt->execute([(int) $user['id']]);
$wishlistCount = (int) $wlStmt->fetchColumn();

$activeTab = (string) get('tab', 'orders');
if (!in_array($activeTab, ['orders', 'tracking', 'details', 'loyalty'], true)) {
    $activeTab = 'orders';
}

$statusLabels = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
];

$openOrders = 0;
$deliveredOrders = 0;
foreach ($orderRows as $o) {
    $st = (string) $o['status'];
    if (in_array($st, ['pending', 'paid', 'processing', 'shipped'], true)) {
        $openOrders++;
    }
    if ($st === 'delivered') {
        $deliveredOrders++;
    }
}

$contactLine = '';
if (!empty($user['email'])) {
    $contactLine = (string) $user['email'];
} elseif (!empty($user['phone'])) {
    $contactLine = (string) $user['phone'];
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="account-page">
  <div class="account-page__inner">
    <header class="account-hero">
      <div class="account-hero__copy">
        <p class="account-hero__eyebrow">Your studio account</p>
        <h1 class="account-hero__title font-display">Hello, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
        <p class="account-hero__meta">
          <?php if ($contactLine !== ''): ?>
            <span><?= e($contactLine) ?></span>
            <span class="account-hero__dot" aria-hidden="true">·</span>
          <?php endif; ?>
          <span><?= (int) $user['loyalty_points'] ?> loyalty points</span>
        </p>
      </div>

      <div class="account-actions" aria-label="Account actions">
        <a href="<?= e(url('index.php?page=wishlist')) ?>" class="account-action">
          <span class="account-action__icon" aria-hidden="true">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21C12 21 4 13.9 4 8.8 4 6.1 6.1 4 8.8 4c1.6 0 3.1.8 3.2 2 .1-1.2 1.6-2 3.2-2C17.9 4 20 6.1 20 8.8c0 5.1-8 12.2-8 12.2z"/></svg>
          </span>
          <span class="account-action__label">Wishlist</span>
          <?php if ($wishlistCount > 0): ?>
            <span class="account-action__badge"><?= $wishlistCount ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= e(url('index.php?page=shop')) ?>" class="account-action">
          <span class="account-action__icon" aria-hidden="true">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
          </span>
          <span class="account-action__label">Shop</span>
        </a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="<?= e(url('admin/index.php')) ?>" class="account-action">
            <span class="account-action__icon" aria-hidden="true">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            </span>
            <span class="account-action__label">Admin</span>
          </a>
        <?php endif; ?>
        <a href="<?= e(url('index.php?page=logout')) ?>"
          class="account-action account-action--danger"
          onclick="return confirm('Sign out of your account?');">
          <span class="account-action__icon" aria-hidden="true">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          </span>
          <span class="account-action__label">Sign out</span>
        </a>
      </div>
    </header>

    <div class="account-stats" aria-label="Account overview">
      <div class="account-stat">
        <span class="account-stat__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </span>
        <div>
          <p class="account-stat__value"><?= count($orderRows) ?></p>
          <p class="account-stat__label">Orders</p>
        </div>
      </div>
      <div class="account-stat">
        <span class="account-stat__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m6 0a2 2 0 104 0"/></svg>
        </span>
        <div>
          <p class="account-stat__value"><?= $openOrders ?></p>
          <p class="account-stat__label">In progress</p>
        </div>
      </div>
      <div class="account-stat">
        <span class="account-stat__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 13l4 4L19 7"/></svg>
        </span>
        <div>
          <p class="account-stat__value"><?= $deliveredOrders ?></p>
          <p class="account-stat__label">Delivered</p>
        </div>
      </div>
      <div class="account-stat">
        <span class="account-stat__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <div>
          <p class="account-stat__value"><?= (int) $user['loyalty_points'] ?></p>
          <p class="account-stat__label">Points</p>
        </div>
      </div>
    </div>

    <div class="account-tabs" data-account-tabs>
      <div class="account-tabs__list" role="tablist" aria-label="Account sections">
        <button type="button" class="account-tabs__btn<?= $activeTab === 'orders' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'orders' ? 'true' : 'false' ?>" data-account-tab="orders" id="tab-orders">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
          Orders
        </button>
        <button type="button" class="account-tabs__btn<?= $activeTab === 'tracking' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'tracking' ? 'true' : 'false' ?>" data-account-tab="tracking" id="tab-tracking">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m6 0a2 2 0 104 0"/></svg>
          Tracking
        </button>
        <button type="button" class="account-tabs__btn<?= $activeTab === 'details' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'details' ? 'true' : 'false' ?>" data-account-tab="details" id="tab-details">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          Details
        </button>
        <button type="button" class="account-tabs__btn<?= $activeTab === 'loyalty' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'loyalty' ? 'true' : 'false' ?>" data-account-tab="loyalty" id="tab-loyalty">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
          Loyalty
        </button>
      </div>

      <div class="account-tabs__panels">
        <div class="account-panel<?= $activeTab === 'orders' ? ' is-active' : '' ?>" role="tabpanel" data-account-panel="orders" aria-labelledby="tab-orders"<?= $activeTab !== 'orders' ? ' hidden' : '' ?>>
          <div class="account-panel__head">
            <div>
              <h2 class="account-panel__title font-display">Orders</h2>
              <p class="account-panel__lead">Track deliveries and review past purchases.</p>
            </div>
            <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink account-panel__cta">Continue shopping</a>
          </div>

          <?php if (!$orderRows): ?>
            <div class="account-empty">
              <span class="account-empty__icon" aria-hidden="true">
                <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
              </span>
              <h3 class="font-display text-2xl mb-2">No orders yet</h3>
              <p class="text-brand-soft mb-5">When you place an order, the details will appear here.</p>
              <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-flex px-6 py-3 text-sm tracking-[0.12em] uppercase">Browse the shop</a>
            </div>
          <?php else: ?>
            <div class="account-orders">
              <?php foreach ($orderRows as $index => $o): ?>
                <?php
                  $oid = (int) $o['id'];
                  $items = $orderItemsById[$oid] ?? [];
                  $itemCount = 0;
                  foreach ($items as $it) {
                      $itemCount += (int) $it['quantity'];
                  }
                  $status = (string) $o['status'];
                  $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                  $trackUrl = tracking_url($o['shipping_carrier'] ?? '', $o['tracking_number'] ?? '');
                  $sym = currency_symbol((string) $o['currency']);
                  $open = $index === 0;
                  $panelId = 'order-panel-' . $oid;
                ?>
                <article class="account-order<?= $open ? ' is-open' : '' ?>" data-account-order>
                  <button
                    type="button"
                    class="account-order__summary"
                    data-account-order-toggle
                    aria-expanded="<?= $open ? 'true' : 'false' ?>"
                    aria-controls="<?= e($panelId) ?>"
                  >
                    <span class="account-order__main">
                      <span class="account-order__number"><?= e((string) $o['order_number']) ?></span>
                      <span class="account-order__meta">
                        <span class="account-order__status account-order__status--<?= e(preg_replace('/[^a-z]/', '', strtolower($status)) ?: 'pending') ?>"><?= e($statusLabel) ?></span>
                        <span><?= e(date('j M Y', strtotime((string) $o['created_at']) ?: 'now')) ?></span>
                        <span><?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></span>
                      </span>
                    </span>
                    <span class="account-order__aside">
                      <span class="account-order__total"><?= e($sym . number_format((float) $o['total'], 2)) ?></span>
                      <span class="account-order__chevron" aria-hidden="true">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7"/></svg>
                      </span>
                    </span>
                  </button>

                  <div class="account-order__details" id="<?= e($panelId) ?>"<?= $open ? '' : ' hidden' ?>>
                    <div class="account-order__toolbar">
                      <div class="account-order__toolbar-status">
                        <span class="account-order__status account-order__status--<?= e(preg_replace('/[^a-z]/', '', strtolower($status)) ?: 'pending') ?>"><?= e($statusLabel) ?></span>
                        <span class="account-order__toolbar-meta">
                          <?= e(date('j M Y', strtotime((string) $o['created_at']) ?: 'now')) ?>
                          <?php if (!empty($o['shipping_carrier'])): ?>
                            · Via <?= e(carrier_label($o['shipping_carrier'])) ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="account-order__toolbar-actions">
                        <a href="<?= e(url('index.php?page=account&tab=tracking')) ?>" class="account-order__action account-order__action--primary">
                          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m6 0a2 2 0 104 0"/></svg>
                          Track order
                        </a>
                        <?php if ($trackUrl): ?>
                          <a href="<?= e($trackUrl) ?>" target="_blank" rel="noopener" class="account-order__action">
                            Track <?= e(carrier_label($o['shipping_carrier'] ?? '')) ?>
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="account-order__progress">
                      <?php
                        $progress = order_tracking_progress($status);
                        $progress = array_values(array_filter(
                            $progress,
                            static fn ($step) => !in_array($step['state'], ['muted'], true)
                        ));
                      ?>
                      <ol class="account-order__rail" aria-label="Order progress">
                        <?php foreach ($progress as $step): ?>
                          <?php if (in_array($step['key'], ['cancelled', 'refunded'], true) || in_array($step['state'], ['done', 'current', 'current-bad', 'upcoming'], true)): ?>
                            <li class="account-order__rail-step is-<?= e($step['state']) ?>">
                              <span class="account-order__rail-dot" aria-hidden="true"></span>
                              <span class="account-order__rail-label"><?= e($step['label']) ?></span>
                            </li>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </ol>
                    </div>

                    <div class="account-order__grid">
                      <div class="account-order__card">
                        <h3 class="account-order__block-title">
                          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 6h16M4 12h16M4 18h10"/></svg>
                          Items
                        </h3>
                        <?php if (!$items): ?>
                          <p class="text-sm text-brand-soft">No line items recorded.</p>
                        <?php else: ?>
                          <ul class="account-order__items">
                            <?php foreach ($items as $item): ?>
                              <li>
                                <div>
                                  <p class="account-order__item-name"><?= e((string) $item['product_name']) ?></p>
                                  <p class="account-order__item-variant">
                                    <?php if (!empty($item['variant_label'])): ?>
                                      <?= e((string) $item['variant_label']) ?> ·
                                    <?php endif; ?>
                                    Qty <?= (int) $item['quantity'] ?>
                                  </p>
                                </div>
                                <span class="account-order__item-price"><?= e($sym . number_format((float) $item['line_total'], 2)) ?></span>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        <?php endif; ?>
                        <div class="account-order__totals">
                          <div><span>Subtotal</span><span><?= e($sym . number_format((float) $o['subtotal'], 2)) ?></span></div>
                          <div><span>Shipping</span><span><?= e($sym . number_format((float) $o['shipping'], 2)) ?></span></div>
                          <?php if ((float) $o['discount'] > 0): ?>
                            <div><span>Discount</span><span>−<?= e($sym . number_format((float) $o['discount'], 2)) ?></span></div>
                          <?php endif; ?>
                          <?php if (!empty($o['gift_card_amount']) && (float) $o['gift_card_amount'] > 0): ?>
                            <div><span>Gift card</span><span>−<?= e($sym . number_format((float) $o['gift_card_amount'], 2)) ?></span></div>
                          <?php endif; ?>
                          <div class="account-order__totals-total"><span>Total</span><span><?= e($sym . number_format((float) $o['total'], 2)) ?></span></div>
                        </div>
                      </div>

                      <div class="account-order__card">
                        <h3 class="account-order__block-title">
                          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                          Delivery
                        </h3>
                        <dl class="account-order__facts">
                          <div>
                            <dt>Recipient</dt>
                            <dd><?= e((string) ($o['shipping_name'] ?: '—')) ?></dd>
                          </div>
                          <div>
                            <dt>Address</dt>
                            <dd>
                              <?php if (!empty($o['shipping_address'])): ?>
                                <?= e((string) $o['shipping_address']) ?>
                              <?php else: ?>
                                —
                              <?php endif; ?>
                            </dd>
                          </div>
                          <div>
                            <dt>City / postcode</dt>
                            <dd>
                              <?php
                                $cityLine = trim(implode(' ', array_filter([
                                    (string) ($o['shipping_city'] ?? ''),
                                    (string) ($o['shipping_postcode'] ?? ''),
                                ])));
                                echo $cityLine !== '' ? e($cityLine) : '—';
                              ?>
                            </dd>
                          </div>
                          <div>
                            <dt>Country</dt>
                            <dd><?= e((string) ($o['shipping_country'] ?: '—')) ?></dd>
                          </div>
                          <?php if (!empty($o['shipping_carrier'])): ?>
                            <div>
                              <dt>Carrier</dt>
                              <dd><?= e(carrier_label($o['shipping_carrier'])) ?></dd>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($o['tracking_number'])): ?>
                            <div>
                              <dt>Tracking no.</dt>
                              <dd class="account-order__mono"><?= e((string) $o['tracking_number']) ?></dd>
                            </div>
                          <?php endif; ?>
                        </dl>
                      </div>

                      <div class="account-order__card">
                        <h3 class="account-order__block-title">
                          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                          Payment
                        </h3>
                        <dl class="account-order__facts">
                          <div>
                            <dt>Method</dt>
                            <dd><?= e(ucfirst((string) ($o['payment_method'] ?: '—'))) ?></dd>
                          </div>
                          <div>
                            <dt>Status</dt>
                            <dd><?= e($statusLabel) ?></dd>
                          </div>
                          <div>
                            <dt>Currency</dt>
                            <dd><?= e((string) $o['currency']) ?></dd>
                          </div>
                          <div>
                            <dt>Amount paid</dt>
                            <dd><?= e($sym . number_format((float) $o['total'], 2)) ?></dd>
                          </div>
                          <?php if (!empty($o['payment_ref'])): ?>
                            <div>
                              <dt>Reference</dt>
                              <dd class="account-order__mono"><?= e((string) $o['payment_ref']) ?></dd>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($o['email'])): ?>
                            <div>
                              <dt>Receipt email</dt>
                              <dd><?= e((string) $o['email']) ?></dd>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($o['phone'])): ?>
                            <div>
                              <dt>Phone</dt>
                              <dd><?= e((string) $o['phone']) ?></dd>
                            </div>
                          <?php endif; ?>
                        </dl>
                      </div>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="account-panel<?= $activeTab === 'tracking' ? ' is-active' : '' ?>" role="tabpanel" data-account-panel="tracking" aria-labelledby="tab-tracking"<?= $activeTab !== 'tracking' ? ' hidden' : '' ?>>
          <div class="account-panel__head">
            <div>
              <h2 class="account-panel__title font-display">Tracking</h2>
              <p class="account-panel__lead">Live progress for every order on your account.</p>
            </div>
            <a href="<?= e(url('index.php?page=track')) ?>" class="success-actions__secondary">Track by phone</a>
          </div>

          <?php if (!$orderRows): ?>
            <div class="account-empty">
              <span class="account-empty__icon" aria-hidden="true">
                <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m6 0a2 2 0 104 0"/></svg>
              </span>
              <h3 class="font-display text-2xl mb-2">Nothing to track yet</h3>
              <p class="text-brand-soft mb-5">Once you place an order, progress steps will appear here.</p>
              <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-flex px-6 py-3 text-sm tracking-[0.12em] uppercase">Browse the shop</a>
            </div>
          <?php else: ?>
            <div class="account-tracking-list">
              <?php foreach ($orderRows as $o): ?>
                <?php render_order_tracker($o); ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="account-panel<?= $activeTab === 'details' ? ' is-active' : '' ?>" role="tabpanel" data-account-panel="details" aria-labelledby="tab-details"<?= $activeTab !== 'details' ? ' hidden' : '' ?>>
          <div class="account-panel__head">
            <div>
              <h2 class="account-panel__title font-display">Account details</h2>
              <p class="account-panel__lead">Your sign-in details and contact info.</p>
            </div>
          </div>

          <div class="account-details">
            <div class="account-detail">
              <span class="account-detail__icon" aria-hidden="true">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </span>
              <div>
                <p class="account-detail__label">Full name</p>
                <p class="account-detail__value"><?= e((string) $user['name']) ?></p>
              </div>
            </div>
            <div class="account-detail">
              <span class="account-detail__icon" aria-hidden="true">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </span>
              <div>
                <p class="account-detail__label">Email</p>
                <p class="account-detail__value"><?= !empty($user['email']) ? e((string) $user['email']) : 'Not added yet' ?></p>
              </div>
            </div>
            <div class="account-detail">
              <span class="account-detail__icon" aria-hidden="true">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              </span>
              <div>
                <p class="account-detail__label">Phone</p>
                <p class="account-detail__value"><?= !empty($user['phone']) ? e((string) $user['phone']) : 'Not added yet' ?></p>
              </div>
            </div>
            <div class="account-detail">
              <span class="account-detail__icon" aria-hidden="true">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </span>
              <div>
                <p class="account-detail__label">Sign-in</p>
                <p class="account-detail__value">
                  <?php if (!empty($user['email']) && !empty($user['phone'])): ?>
                    Email or phone + password
                  <?php elseif (!empty($user['phone'])): ?>
                    Phone + password
                  <?php else: ?>
                    Email + password
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="account-panel<?= $activeTab === 'loyalty' ? ' is-active' : '' ?>" role="tabpanel" data-account-panel="loyalty" aria-labelledby="tab-loyalty"<?= $activeTab !== 'loyalty' ? ' hidden' : '' ?>>
          <div class="account-panel__head">
            <div>
              <h2 class="account-panel__title font-display">Loyalty</h2>
              <p class="account-panel__lead">Points earned from your Claudia Darlene orders.</p>
            </div>
          </div>

          <div class="account-loyalty">
            <div class="account-loyalty__score">
              <p class="account-loyalty__points font-display"><?= (int) $user['loyalty_points'] ?></p>
              <p class="account-loyalty__caption">points available</p>
            </div>
            <div class="account-loyalty__copy">
              <p>Loyalty points accumulate as you shop. Keep an eye on this tab for balances and future rewards.</p>
              <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-blush inline-flex px-5 py-2.5 text-sm tracking-[0.12em] uppercase mt-5">Earn more points</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
