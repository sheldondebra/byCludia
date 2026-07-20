<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();
require __DIR__ . '/_metrics.php';

$days = metrics_range_days();

$productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$inactiveProducts = (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 0')->fetchColumn();
$orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$customerCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

$curOrders = metrics_orders_in_window($days);
$prevOrders = metrics_orders_in_window($days, 1);
$curRevenue = metrics_revenue($curOrders);
$prevRevenue = metrics_revenue($prevOrders);
$revenueTrend = metrics_trend($curRevenue, $prevRevenue);
$ordersTrend = metrics_trend((float) count($curOrders), (float) count($prevOrders));

$lowStock = metrics_low_stock_count(5);
$pending = metrics_status_count('pending');

$revSeries = metrics_revenue_series($days);
$statusBreak = metrics_status_breakdown($days);
$payBreak = metrics_payment_breakdown($days);
$topProducts = metrics_top_products($days);

$recent = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
  <h1 class="font-display text-4xl">Dashboard</h1>
  <div class="flex items-center gap-2 text-sm">
    <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label): ?>
      <a href="?range=<?= $d ?>"
        class="rounded-full px-4 py-2 border <?= $days === $d ? 'bg-stone-900 text-white border-stone-900' : 'bg-white border-stone-200 text-stone-600 hover:border-stone-400' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($lowStock > 0 || $pending > 0): ?>
<div class="grid sm:grid-cols-2 gap-4 mb-8">
  <?php if ($lowStock > 0): ?>
    <a href="products.php" class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl px-5 py-4 text-sm">
      <?= admin_icon('alert-triangle', 'w-5 h-5') ?>
      <span><strong><?= $lowStock ?></strong> product variant<?= $lowStock === 1 ? '' : 's' ?> low on stock (&le; 5)</span>
    </a>
  <?php endif; ?>
  <?php if ($pending > 0): ?>
    <a href="orders.php?status=pending" class="flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-4 text-sm">
      <?= admin_icon('clock', 'w-5 h-5') ?>
      <span><strong><?= $pending ?></strong> order<?= $pending === 1 ? '' : 's' ?> pending</span>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
  <?php
  $cards = [
      ['Revenue (GBP)', '&pound;' . number_format($curRevenue, 2), 'banknote', metrics_trend_badge($revenueTrend)],
      ['Orders', (string) count($curOrders), 'receipt-text', metrics_trend_badge($ordersTrend)],
      ['Customers', (string) $customerCount, 'users', ''],
      ['Products', (string) $productCount, 'package', '<span class="text-xs text-stone-400">' . $inactiveProducts . ' inactive</span>'],
  ];
  foreach ($cards as $card): ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <div class="flex items-center justify-between">
        <p class="text-xs tracking-widest uppercase text-stone-400"><?= $card[0] ?></p>
        <span class="rounded-lg bg-[#F3C4C4]/40 text-stone-700 p-2"><?= admin_icon($card[2]) ?></span>
      </div>
      <p class="text-3xl font-display mt-3"><?= $card[1] ?></p>
      <div class="mt-1"><?= $card[3] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-4">
  <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200 p-5">
    <h2 class="font-display text-2xl mb-4">Revenue over time</h2>
    <canvas id="revenueChart" height="110"></canvas>
  </div>
  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h2 class="font-display text-2xl mb-4">Orders by status</h2>
    <canvas id="statusChart" height="200"></canvas>
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-10">
  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h2 class="font-display text-2xl mb-4">Top products</h2>
    <canvas id="topProductsChart" height="160"></canvas>
  </div>
  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h2 class="font-display text-2xl mb-4">Payment methods</h2>
    <canvas id="paymentChart" height="160"></canvas>
  </div>
</div>

<h2 class="font-display text-2xl mb-4">Recent orders</h2>
<div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Order</th>
        <th class="px-4 py-3">Customer</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $o): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3"><a class="text-stone-900 underline" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a></td>
          <td class="px-4 py-3"><?= e($o['email']) ?></td>
          <td class="px-4 py-3"><?= admin_status_badge((string) $o['status']) ?></td>
          <td class="px-4 py-3"><?= e($o['currency'] . ' ' . number_format((float) $o['total'], 2)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
        <tr><td colspan="4" class="px-4 py-8 text-center text-stone-400">No orders yet</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
const blush = '#F3C4C4';
const palette = ['#16a34a', '#0ea5e9', '#6366f1', '#f59e0b', '#e11d48', '#a8a29e', '#22c55e'];

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($revSeries['labels']) ?>,
    datasets: [{
      label: 'Revenue (GBP)',
      data: <?= json_encode($revSeries['data']) ?>,
      borderColor: blush,
      backgroundColor: 'rgba(243,196,196,0.2)',
      fill: true,
      tension: 0.35,
      pointRadius: 2,
    }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($statusBreak)) ?>,
    datasets: [{ data: <?= json_encode(array_values($statusBreak)) ?>, backgroundColor: palette }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('topProductsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($p) => $p['name'], $topProducts)) ?>,
    datasets: [{ label: 'Revenue (GBP)', data: <?= json_encode(array_map(fn($p) => $p['total'], $topProducts)) ?>, backgroundColor: blush }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('paymentChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($payBreak)) ?>,
    datasets: [{ data: <?= json_encode(array_values($payBreak)) ?>, backgroundColor: palette }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
