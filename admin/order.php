<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$id = (int) get('id', 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    header('Location: orders.php');
    exit;
}

$notice = null;
if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    if (post('action') === 'resend_notify') {
        notify_order_paid($id);
        flash('success', 'Confirmation email/SMS re-sent (if channels are enabled).');
        header('Location: order.php?id=' . $id);
        exit;
    }
    $status = (string) post('status');
    $tracking = trim((string) post('tracking_number'));
    $carrier = (string) post('shipping_carrier');
    $allowedCarriers = ['', 'dhl', 'fedex', 'standard'];
    if (!in_array($carrier, $allowedCarriers, true)) {
        $carrier = '';
    }
    $allowed = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];
    if (in_array($status, $allowed, true)) {
        $previousStatus = (string) $order['status'];
        db()->prepare('UPDATE orders SET status = ?, tracking_number = ?, shipping_carrier = ? WHERE id = ?')
            ->execute([$status, $tracking !== '' ? $tracking : null, $carrier !== '' ? $carrier : null, $id]);

        if ($previousStatus !== $status) {
            $mailed = notify_order_status_changed($id, $previousStatus, $status);
            if ($mailed) {
                flash('success', 'Order updated and customer emailed.');
            } else {
                flash('success', 'Order updated.' . (!empty($order['email']) ? ' Status email skipped (mail disabled or send failed).' : ' No customer email on file.'));
            }
        } else {
            flash('success', 'Order updated.');
        }

        header('Location: order.php?id=' . $id);
        exit;
    }
}

$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$orderItems = $items->fetchAll();

$payStmt = db()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC');
$payStmt->execute([$id]);
$payments = $payStmt->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<a href="orders.php" class="text-sm underline mb-4 inline-block">← Orders</a>
<h1 class="font-display text-4xl mb-2"><?= htmlspecialchars($order['order_number']) ?></h1>
<p class="text-stone-500 mb-4"><?= htmlspecialchars($order['email']) ?><?= $order['phone'] ? ' · ' . htmlspecialchars((string) $order['phone']) : '' ?> · <?= htmlspecialchars($order['created_at']) ?></p>

<?php if ($m = flash('success')): ?><div class="mb-6 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars($m) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl border border-stone-200 p-6">
    <h2 class="font-display text-2xl mb-4">Items</h2>
    <ul class="space-y-3 text-sm">
      <?php foreach ($orderItems as $item): ?>
        <li class="flex justify-between gap-3">
          <span><?= htmlspecialchars($item['product_name']) ?> (<?= htmlspecialchars((string) $item['variant_label']) ?>) × <?= (int) $item['quantity'] ?></span>
          <span><?= number_format((float) $item['line_total'], 2) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="mt-4 pt-4 border-t text-sm space-y-1">
      <div class="flex justify-between"><span>Subtotal</span><span><?= number_format((float) $order['subtotal'], 2) ?></span></div>
      <div class="flex justify-between"><span>Shipping</span><span><?= number_format((float) $order['shipping'], 2) ?></span></div>
      <div class="flex justify-between"><span>Discount</span><span><?= number_format((float) $order['discount'], 2) ?></span></div>
      <div class="flex justify-between font-medium"><span>Total (<?= htmlspecialchars($order['currency']) ?>)</span><span><?= number_format((float) $order['total'], 2) ?></span></div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-display text-2xl">Fulfillment</h2>
    <p class="text-sm text-stone-500"><?= htmlspecialchars((string) $order['shipping_name']) ?><br><?= nl2br(htmlspecialchars((string) $order['shipping_address'])) ?><br><?= htmlspecialchars((string) $order['shipping_city']) ?> <?= htmlspecialchars((string) $order['shipping_postcode']) ?><br><?= htmlspecialchars((string) $order['shipping_country']) ?></p>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <label class="text-xs text-stone-500 block">Status</label>
      <select name="status" class="w-full rounded-xl border px-4 py-3 text-sm">
        <?php foreach (['pending','paid','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-stone-500 block mb-1">Carrier</label>
          <select name="shipping_carrier" class="w-full rounded-xl border px-4 py-3 text-sm">
            <?php $curCarrier = strtolower((string) ($order['shipping_carrier'] ?? '')); ?>
            <option value="">— none —</option>
            <?php foreach (['dhl' => 'DHL', 'fedex' => 'FedEx', 'standard' => 'Standard'] as $cv => $cl): ?>
              <option value="<?= $cv ?>" <?= $curCarrier === $cv ? 'selected' : '' ?>><?= $cl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-xs text-stone-500 block mb-1">Tracking number</label>
          <input name="tracking_number" value="<?= htmlspecialchars((string) ($order['tracking_number'] ?? '')) ?>" placeholder="e.g. 1234567890" class="w-full rounded-xl border px-4 py-3 text-sm">
        </div>
      </div>
      <?php $trackUrl = tracking_url($order['shipping_carrier'] ?? '', $order['tracking_number'] ?? ''); ?>
      <?php if ($trackUrl): ?>
        <a href="<?= htmlspecialchars($trackUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline"><?= admin_icon('external-link', 'w-4 h-4') ?> Track <?= htmlspecialchars(carrier_label($order['shipping_carrier'] ?? '')) ?> shipment</a>
      <?php endif; ?>
      <button class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm">Update order</button>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4 lg:col-span-2">
    <div class="flex items-center justify-between">
      <h2 class="font-display text-2xl">Payment &amp; transactions</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend_notify"><button class="rounded-full border border-stone-300 px-4 py-2 text-xs hover:bg-stone-100">Resend confirmation</button></form>
    </div>
    <div class="grid sm:grid-cols-3 gap-4 text-sm">
      <div><span class="text-stone-400 text-xs block">Method</span><?= htmlspecialchars((string) ($order['payment_method'] ?: '—')) ?></div>
      <div><span class="text-stone-400 text-xs block">Reference</span><span class="font-mono text-xs"><?= htmlspecialchars((string) ($order['payment_ref'] ?: '—')) ?></span></div>
      <div><span class="text-stone-400 text-xs block">Status</span><?= htmlspecialchars((string) $order['status']) ?></div>
    </div>
    <?php if ($payments): ?>
      <table class="w-full text-sm border-t border-stone-100 mt-2">
        <thead class="text-left text-stone-400 text-xs"><tr><th class="py-2">Provider</th><th>Reference</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr class="border-t border-stone-100">
              <td class="py-2 capitalize"><?= htmlspecialchars((string) $p['provider']) ?></td>
              <td class="font-mono text-xs"><?= htmlspecialchars((string) $p['provider_ref']) ?></td>
              <td><?= htmlspecialchars((string) $p['currency']) ?> <?= number_format((float) $p['amount'], 2) ?></td>
              <td><?= htmlspecialchars((string) $p['status']) ?></td>
              <td class="text-stone-500"><?= htmlspecialchars((string) $p['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-sm text-stone-400">No transactions recorded yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
