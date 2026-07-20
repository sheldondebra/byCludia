<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
$revenueStatuses = ['paid', 'processing', 'shipped', 'delivered'];

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $id = (int) post('id');
    if (post('action') === 'ship' && $id) {
        db()->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")->execute([$id]);
    }
    if (post('action') === 'set_status' && $id) {
        $newStatus = (string) post('status_value');
        if (in_array($newStatus, $statuses, true)) {
            db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        }
    }
    $qs = [];
    foreach (['q', 'status', 'from', 'to', 'payment', 'sort'] as $k) {
        if (($val = trim((string) post('return_' . $k))) !== '') {
            $qs[$k] = $val;
        }
    }
    header('Location: orders.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

$q = trim((string) get('q', ''));
$status = (string) get('status', '');
$status = in_array($status, $statuses, true) ? $status : '';
$from = trim((string) get('from', ''));
$to = trim((string) get('to', ''));
$payment = trim((string) get('payment', ''));
$sort = (string) get('sort', 'newest');

// Build filter WHERE
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR email LIKE ? OR shipping_name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($from !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $to;
}
if ($payment !== '') {
    $where[] = 'payment_method = ?';
    $params[] = $payment;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$orderBy = match ($sort) {
    'oldest' => ' ORDER BY id ASC',
    'total_high' => ' ORDER BY total DESC',
    'total_low' => ' ORDER BY total ASC',
    default => ' ORDER BY id DESC',
};

$sql = 'SELECT o.*, (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count FROM orders o'
    . $whereSql . $orderBy . ' LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// CSV export (respects filters)
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Customer', 'Email', 'Items', 'Payment', 'Status', 'Currency', 'Total'], ',', '"', '\\');
    foreach ($orders as $o) {
        fputcsv($out, [
            $o['order_number'], $o['created_at'], $o['shipping_name'] ?? '', $o['email'],
            $o['item_count'], $o['payment_method'] ?? '', $o['status'], $o['currency'], number_format((float) $o['total'], 2),
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// Stats (all-time)
$statusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM orders GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int) $r['c'];
}
$totalOrders = array_sum($statusCounts);
$revPlaceholders = implode(',', array_fill(0, count($revenueStatuses), '?'));
$revStmt = db()->prepare('SELECT COALESCE(SUM(total),0) rev, COUNT(*) c FROM orders WHERE status IN (' . $revPlaceholders . ')');
$revStmt->execute($revenueStatuses);
$revRow = $revStmt->fetch();
$revenue = (float) $revRow['rev'];
$paidOrders = (int) $revRow['c'];
$avgOrder = $paidOrders > 0 ? $revenue / $paidOrders : 0;
$needsAttention = ($statusCounts['pending'] ?? 0) + ($statusCounts['paid'] ?? 0) + ($statusCounts['processing'] ?? 0);
$todayStmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?');
$todayStmt->execute([date('Y-m-d')]);
$todayOrders = (int) $todayStmt->fetchColumn();

$paymentMethods = [];
foreach (db()->query("SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method <> ''") as $r) {
    $paymentMethods[] = $r['payment_method'];
}

$filterQs = array_filter(['q' => $q, 'status' => $status, 'from' => $from, 'to' => $to, 'payment' => $payment, 'sort' => $sort !== 'newest' ? $sort : '']);
$hasFilters = !empty($filterQs);

function admin_stat_card(string $label, string $value, string $icon, string $tone = 'stone', string $sub = ''): string
{
    $tones = [
        'stone' => 'bg-stone-100 text-stone-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700',
        'blush' => 'bg-[#F3C4C4]/40 text-stone-800',
        'indigo' => 'bg-indigo-100 text-indigo-700',
    ];
    $badge = $tones[$tone] ?? $tones['stone'];
    $subHtml = $sub !== '' ? '<p class="text-xs text-stone-400 mt-1">' . e($sub) . '</p>' : '';
    return '<div class="bg-white rounded-2xl border border-stone-200 p-4 flex items-center gap-3">'
        . '<span class="w-10 h-10 rounded-xl ' . $badge . ' flex items-center justify-center shrink-0">' . admin_icon($icon, 'w-5 h-5') . '</span>'
        . '<div><p class="text-xs text-stone-500">' . e($label) . '</p><p class="text-xl font-semibold">' . $value . '</p>' . $subHtml . '</div>'
        . '</div>';
}

require __DIR__ . '/_layout_top.php';
?>

<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
  <div>
    <h1 class="font-display text-4xl">Orders</h1>
    <p class="text-sm text-stone-500 mt-1"><?= number_format($totalOrders) ?> orders all-time</p>
  </div>
  <a href="orders.php?<?= e(http_build_query(array_merge($filterQs, ['export' => 'csv']))) ?>" class="flex items-center gap-2 rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 transition"><?= admin_icon('download', 'w-4 h-4') ?> Export CSV</a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
  <?= admin_stat_card('Revenue', money($revenue), 'pound-sterling', 'emerald', $paidOrders . ' paid orders') ?>
  <?= admin_stat_card('Avg order', money($avgOrder), 'trending-up', 'indigo') ?>
  <?= admin_stat_card('Needs attention', (string) $needsAttention, 'alert-circle', 'amber', 'pending / processing') ?>
  <?= admin_stat_card('Today', (string) $todayOrders, 'calendar', 'blush') ?>
  <?= admin_stat_card('Cancelled', (string) (($statusCounts['cancelled'] ?? 0) + ($statusCounts['refunded'] ?? 0)), 'x-circle', 'rose') ?>
</div>

<!-- Status chips -->
<div class="flex flex-wrap gap-2 mb-5">
  <a href="orders.php<?= e($q || $from || $to || $payment ? '?' . http_build_query(array_filter(['q' => $q, 'from' => $from, 'to' => $to, 'payment' => $payment])) : '') ?>" class="rounded-full px-4 py-1.5 text-sm border <?= $status === '' ? 'bg-stone-900 text-white border-stone-900' : 'border-stone-200 hover:bg-stone-100' ?>">All <span class="opacity-60"><?= $totalOrders ?></span></a>
  <?php foreach ($statuses as $s): ?>
    <a href="orders.php?<?= e(http_build_query(array_filter(['status' => $s, 'q' => $q, 'from' => $from, 'to' => $to, 'payment' => $payment]))) ?>" class="rounded-full px-4 py-1.5 text-sm border capitalize <?= $status === $s ? 'bg-stone-900 text-white border-stone-900' : 'border-stone-200 hover:bg-stone-100' ?>"><?= e($s) ?> <span class="opacity-60"><?= $statusCounts[$s] ?? 0 ?></span></a>
  <?php endforeach; ?>
</div>

<form method="get" class="mb-6 grid sm:grid-cols-2 lg:grid-cols-6 gap-3">
  <div class="relative lg:col-span-2">
    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
    <input name="q" value="<?= e($q) ?>" placeholder="Order #, email or name…"
      class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  </div>
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <input type="date" name="from" value="<?= e($from) ?>" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  <input type="date" name="to" value="<?= e($to) ?>" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  <select name="payment" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    <option value="">All payments</option>
    <?php foreach ($paymentMethods as $pm): ?>
      <option value="<?= e($pm) ?>" <?= $payment === $pm ? 'selected' : '' ?>><?= e(ucfirst($pm)) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="flex gap-2">
    <select name="sort" class="flex-1 rounded-full border border-stone-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      <?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'total_high' => 'Total ↓', 'total_low' => 'Total ↑'] as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <button class="rounded-full bg-stone-900 text-white px-4 py-2.5 text-sm hover:bg-stone-800 transition">Go</button>
  </div>
</form>

<div class="flex items-center justify-between mb-3">
  <p class="text-sm text-stone-500"><?= count($orders) ?> result<?= count($orders) === 1 ? '' : 's' ?><?= count($orders) === 500 ? ' (showing latest 500)' : '' ?></p>
  <?php if ($hasFilters): ?>
    <a href="orders.php" class="text-sm text-stone-500 hover:text-stone-900 flex items-center gap-1"><?= admin_icon('x', 'w-4 h-4') ?> Clear filters</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[920px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Order</th>
        <th class="px-4 py-3">Date</th>
        <th class="px-4 py-3">Customer</th>
        <th class="px-4 py-3">Items</th>
        <th class="px-4 py-3">Payment</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Total</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3"><a class="font-medium hover:underline" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a></td>
          <td class="px-4 py-3 text-stone-500 whitespace-nowrap"><?= e(date('d M Y', strtotime((string) $o['created_at']))) ?><span class="block text-xs text-stone-400"><?= e(date('H:i', strtotime((string) $o['created_at']))) ?></span></td>
          <td class="px-4 py-3">
            <span class="block"><?= e($o['shipping_name'] ?: '—') ?></span>
            <span class="block text-xs text-stone-400"><?= e($o['email']) ?></span>
          </td>
          <td class="px-4 py-3 text-stone-600"><?= (int) $o['item_count'] ?></td>
          <td class="px-4 py-3 capitalize text-stone-600"><?= e((string) $o['payment_method'] ?: '—') ?></td>
          <td class="px-4 py-3"><?= admin_status_badge((string) $o['status']) ?></td>
          <td class="px-4 py-3 font-medium tabular-nums whitespace-nowrap"><?= e($o['currency'] . ' ' . number_format((float) $o['total'], 2)) ?></td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-2">
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="return_q" value="<?= e($q) ?>">
                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                <input type="hidden" name="return_from" value="<?= e($from) ?>">
                <input type="hidden" name="return_to" value="<?= e($to) ?>">
                <input type="hidden" name="return_payment" value="<?= e($payment) ?>">
                <input type="hidden" name="return_sort" value="<?= e($sort) ?>">
                <select name="status_value" onchange="this.form.submit()" class="rounded-lg border border-stone-200 px-2 py-1.5 text-xs bg-white cursor-pointer">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <a class="p-1.5 rounded-lg text-stone-500 hover:text-stone-900 hover:bg-stone-100" title="View" href="order.php?id=<?= (int) $o['id'] ?>"><?= admin_icon('eye') ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
        <tr><td colspan="8" class="px-4 py-10 text-center text-stone-400"><?= $hasFilters ? 'No orders match your filters' : 'No orders yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
