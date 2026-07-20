# Admin Dashboard Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the basic PHP admin area into a premium operations panel with icons, search, Chart.js graphs, date-range filters, low-stock/pending alerts, and quick actions.

**Architecture:** Server-rendered PHP (no build step). A shared layout adds Lucide icons + Chart.js via CDN and an active-nav sidebar. A reusable metrics helper aggregates data in PHP (portable across SQLite/MySQL). The dashboard renders stat cards, alerts, and four Chart.js graphs fed by `json_encode`. List pages gain `?q=`/`?status=` filters, status badges, and CSRF-protected quick actions.

**Tech Stack:** PHP 8+, PDO (SQLite local / MySQL prod), Tailwind CSS (CDN), Chart.js (CDN), Lucide icons (CDN).

**No automated test harness exists in this project.** Each task therefore uses concrete manual verification (load a URL, confirm specific rendered output / no PHP warnings) instead of unit tests. The dev server runs via `php -S localhost:8080` from the project root, admin at `http://localhost:8080/admin/` (login `admin@byclaudiadarlene.com` / `Admin123!`).

---

## File Structure

- **Modify** `includes/helpers.php` — add `admin_status_badge()`, `admin_active_nav()`, `admin_icon()`.
- **Create** `admin/_metrics.php` — reusable dashboard aggregation functions (date range, stats, trends, chart datasets, alerts).
- **Modify** `admin/_layout_top.php` — icons in sidebar, active-nav highlight, top search bar, Chart.js + Lucide CDN.
- **Modify** `admin/_layout_bottom.php` — `lucide.createIcons()` init.
- **Modify** `admin/index.php` — stat cards + trends, alerts, 4 charts, restyled recent orders.
- **Modify** `admin/products.php` — search, stock column, status badges, icon quick actions.
- **Modify** `admin/orders.php` — search + status filter, badges, "Mark shipped" quick action.
- **Modify** `admin/customers.php` — search, order count + total spent, detail link.
- **Create** `admin/search.php` — combined global search across orders/products/customers.

---

## Task 1: Shared admin UI helpers

**Files:**
- Modify: `includes/helpers.php` (append before the closing of file, after `active_nav()`)

- [ ] **Step 1: Add the three helper functions**

Append to `includes/helpers.php`:

```php
/**
 * Colored status pill for admin order statuses.
 */
function admin_status_badge(string $status): string
{
    $map = [
        'pending'    => 'bg-amber-100 text-amber-800',
        'paid'       => 'bg-emerald-100 text-emerald-800',
        'processing' => 'bg-sky-100 text-sky-800',
        'shipped'    => 'bg-indigo-100 text-indigo-800',
        'delivered'  => 'bg-green-100 text-green-800',
        'cancelled'  => 'bg-rose-100 text-rose-700',
        'refunded'   => 'bg-stone-200 text-stone-700',
    ];
    $classes = $map[$status] ?? 'bg-stone-100 text-stone-600';
    return '<span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium capitalize ' . $classes . '">'
        . e($status) . '</span>';
}

/**
 * Active-nav classes for the admin sidebar based on the current script filename.
 */
function admin_active_nav(string $file): string
{
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $active = 'flex items-center gap-3 rounded-lg px-3 py-2 bg-[#F3C4C4] text-stone-900 font-semibold';
    $idle   = 'flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5';
    return $current === $file ? $active : $idle;
}

/**
 * Emit a Lucide icon element. Rendered client-side by lucide.createIcons().
 */
function admin_icon(string $name, string $classes = 'w-4 h-4'): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($classes) . '"></i>';
}
```

- [ ] **Step 2: Verify no PHP syntax errors**

Run: `php -l includes/helpers.php`
Expected: `No syntax errors detected in includes/helpers.php`

- [ ] **Step 3: Commit**

```bash
git add includes/helpers.php
git commit -m "feat(admin): add status badge, active-nav, and icon helpers"
```

---

## Task 2: Reusable dashboard metrics helper

**Files:**
- Create: `admin/_metrics.php`

Aggregation is done in PHP after simple, portable `SELECT`s so the same code runs on SQLite (local) and MySQL (prod). Dates are compared as strings in `YYYY-MM-DD` form via `substr(created_at, 0, 10)`, which both engines store compatibly.

- [ ] **Step 1: Create `admin/_metrics.php`**

```php
<?php
declare(strict_types=1);

/** Allowed range presets in days. */
function metrics_range_days(): int
{
    $allowed = [7, 30, 90];
    $r = (int) get('range', 30);
    return in_array($r, $allowed, true) ? $r : 30;
}

/** Statuses that count as realized revenue. */
function metrics_revenue_statuses(): array
{
    return ['paid', 'processing', 'shipped', 'delivered'];
}

/** Fetch all orders (id, status, total, currency, payment_method, email, order_number, created_at). */
function metrics_all_orders(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query(
            'SELECT id, order_number, email, status, total, currency, payment_method, created_at FROM orders'
        )->fetchAll();
    }
    return $cache;
}

/** Orders whose created_at date is within the last $days (inclusive of today). */
function metrics_orders_in_window(int $days, int $offsetPeriods = 0): array
{
    $end = new DateTimeImmutable('today +1 day');
    $start = $end->modify('-' . $days . ' days');
    if ($offsetPeriods > 0) {
        $end = $start;
        $start = $end->modify('-' . $days . ' days');
    }
    $startStr = $start->format('Y-m-d');
    $endStr = $end->format('Y-m-d');
    $out = [];
    foreach (metrics_all_orders() as $o) {
        $d = substr((string) $o['created_at'], 0, 10);
        if ($d >= $startStr && $d < $endStr) {
            $out[] = $o;
        }
    }
    return $out;
}

/** Sum of totals for revenue-status orders in a set. */
function metrics_revenue(array $orders): float
{
    $statuses = metrics_revenue_statuses();
    $sum = 0.0;
    foreach ($orders as $o) {
        if (in_array($o['status'], $statuses, true)) {
            $sum += (float) $o['total'];
        }
    }
    return $sum;
}

/** Percent change vs previous; returns null when previous is 0. */
function metrics_trend(float $current, float $previous): ?float
{
    if ($previous <= 0.0) {
        return null;
    }
    return (($current - $previous) / $previous) * 100.0;
}

/** Render a trend badge (▲/▼ %). */
function metrics_trend_badge(?float $pct): string
{
    if ($pct === null) {
        return '<span class="text-xs text-stone-400">—</span>';
    }
    $up = $pct >= 0;
    $arrow = $up ? '&#9650;' : '&#9660;';
    $color = $up ? 'text-emerald-600' : 'text-rose-600';
    return '<span class="text-xs ' . $color . '">' . $arrow . ' ' . number_format(abs($pct), 1) . '%</span>';
}

/** Daily revenue series for the window: ['labels'=>[], 'data'=>[]]. */
function metrics_revenue_series(int $days): array
{
    $statuses = metrics_revenue_statuses();
    $buckets = [];
    $end = new DateTimeImmutable('today');
    for ($i = $days - 1; $i >= 0; $i--) {
        $key = $end->modify('-' . $i . ' days')->format('Y-m-d');
        $buckets[$key] = 0.0;
    }
    foreach (metrics_orders_in_window($days) as $o) {
        if (!in_array($o['status'], $statuses, true)) {
            continue;
        }
        $d = substr((string) $o['created_at'], 0, 10);
        if (isset($buckets[$d])) {
            $buckets[$d] += (float) $o['total'];
        }
    }
    return [
        'labels' => array_map(fn($k) => date('j M', strtotime($k)), array_keys($buckets)),
        'data'   => array_map(fn($v) => round($v, 2), array_values($buckets)),
    ];
}

/** Count orders by status within the window. */
function metrics_status_breakdown(int $days): array
{
    $counts = [];
    foreach (metrics_orders_in_window($days) as $o) {
        $counts[$o['status']] = ($counts[$o['status']] ?? 0) + 1;
    }
    return $counts;
}

/** Count orders by payment_method within the window. */
function metrics_payment_breakdown(int $days): array
{
    $counts = [];
    foreach (metrics_orders_in_window($days) as $o) {
        $pm = $o['payment_method'] ?: 'unknown';
        $counts[$pm] = ($counts[$pm] ?? 0) + 1;
    }
    return $counts;
}

/** Top products by revenue (line_total) within the window. Returns [['name'=>, 'total'=>], ...]. */
function metrics_top_products(int $days, int $limit = 6): array
{
    $ids = array_map(fn($o) => (int) $o['id'], metrics_orders_in_window($days));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT product_name, SUM(line_total) AS total FROM order_items '
        . 'WHERE order_id IN (' . $placeholders . ') GROUP BY product_name ORDER BY total DESC LIMIT ' . (int) $limit
    );
    $stmt->execute($ids);
    return array_map(
        fn($r) => ['name' => (string) $r['product_name'], 'total' => round((float) $r['total'], 2)],
        $stmt->fetchAll()
    );
}

/** Count variants with stock at or below threshold. */
function metrics_low_stock_count(int $threshold = 5): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM product_variants WHERE stock <= ?');
    $s->execute([$threshold]);
    return (int) $s->fetchColumn();
}

/** Count orders with a given status. */
function metrics_status_count(string $status): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $s->execute([$status]);
    return (int) $s->fetchColumn();
}
```

- [ ] **Step 2: Verify no PHP syntax errors**

Run: `php -l admin/_metrics.php`
Expected: `No syntax errors detected in admin/_metrics.php`

- [ ] **Step 3: Commit**

```bash
git add admin/_metrics.php
git commit -m "feat(admin): add reusable dashboard metrics helper"
```

---

## Task 3: Rework the shared admin layout (icons, active nav, search bar, CDN)

**Files:**
- Modify: `admin/_layout_top.php` (full rewrite)
- Modify: `admin/_layout_bottom.php` (full rewrite)

- [ ] **Step 1: Rewrite `admin/_layout_top.php`**

```php
<?php
declare(strict_types=1);
$user = current_user();
$adminQ = trim((string) get('q', ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – Claudia Darlene</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: Outfit, system-ui, sans-serif; }
    .font-display { font-family: 'Cormorant Garamond', Georgia, serif; }
  </style>
</head>
<body class="bg-[#FBF7F2] text-stone-900 min-h-screen">
  <div class="flex min-h-screen">
    <aside class="w-60 bg-stone-900 text-white p-6 hidden md:block">
      <p class="font-display text-2xl mb-8 flex items-center gap-2"><?= admin_icon('sparkles', 'w-5 h-5 text-[#F3C4C4]') ?> CD Admin</p>
      <nav class="space-y-1 text-sm">
        <a class="<?= admin_active_nav('index.php') ?>" href="index.php"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
        <a class="<?= admin_active_nav('products.php') ?>" href="products.php"><?= admin_icon('package') ?> Products</a>
        <a class="<?= admin_active_nav('orders.php') ?>" href="orders.php"><?= admin_icon('receipt-text') ?> Orders</a>
        <a class="<?= admin_active_nav('customers.php') ?>" href="customers.php"><?= admin_icon('users') ?> Customers</a>
        <a class="<?= admin_active_nav('settings.php') ?>" href="settings.php"><?= admin_icon('settings') ?> Settings</a>
        <div class="pt-4 mt-4 border-t border-white/10 space-y-1">
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="../index.php" target="_blank"><?= admin_icon('external-link') ?> View store</a>
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="../index.php?page=logout"><?= admin_icon('log-out') ?> Logout</a>
        </div>
      </nav>
    </aside>
    <main class="flex-1 p-6 sm:p-10">
      <div class="md:hidden mb-6 flex gap-4 text-sm overflow-x-auto">
        <a href="index.php" class="flex items-center gap-1"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
        <a href="products.php" class="flex items-center gap-1"><?= admin_icon('package') ?> Products</a>
        <a href="orders.php" class="flex items-center gap-1"><?= admin_icon('receipt-text') ?> Orders</a>
        <a href="customers.php" class="flex items-center gap-1"><?= admin_icon('users') ?> Customers</a>
        <a href="settings.php" class="flex items-center gap-1"><?= admin_icon('settings') ?> Settings</a>
      </div>

      <form action="search.php" method="get" class="mb-8 max-w-xl relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
        <input name="q" value="<?= e($adminQ) ?>" placeholder="Search orders, products, customers…"
          class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </form>
```

- [ ] **Step 2: Rewrite `admin/_layout_bottom.php`**

```php
    </main>
  </div>
  <script>
    if (window.lucide) { lucide.createIcons(); }
  </script>
</body>
</html>
```

- [ ] **Step 3: Verify syntax + render**

Run: `php -l admin/_layout_top.php && php -l admin/_layout_bottom.php`
Expected: `No syntax errors detected` for both.

Then load `http://localhost:8080/admin/` (logged in). Expected: dark sidebar with visible icons next to each item, the current page highlighted with a blush pill, and a search bar with a magnifier icon at the top of the content area. No broken-image/box glyphs (icons render as SVG).

- [ ] **Step 4: Commit**

```bash
git add admin/_layout_top.php admin/_layout_bottom.php
git commit -m "feat(admin): sidebar icons, active nav, global search bar, Chart.js/Lucide CDN"
```

---

## Task 4: Rebuild the dashboard home

**Files:**
- Modify: `admin/index.php` (full rewrite)

- [ ] **Step 1: Rewrite `admin/index.php`**

```php
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
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/index.php`
Expected: `No syntax errors detected in admin/index.php`

- [ ] **Step 3: Verify render**

Load `http://localhost:8080/admin/`. Expected:
- Four stat cards with icons; Revenue and Orders show a ▲/▼ % (or `—` if no prior data).
- Alert strips appear if there are low-stock variants or pending orders (seed data has orders, so at least verify no PHP warnings if none appear).
- Four charts render (revenue line, status doughnut, top-products horizontal bar, payment doughnut). Empty datasets still render an empty chart, not an error.
- Date-range buttons (7/30/90) switch the active pill and change the numbers; URL gains `?range=`.
- Recent orders table shows colored status badges.

Check the browser console: no Chart.js errors. Check the PHP server terminal: no warnings/notices.

- [ ] **Step 4: Commit**

```bash
git add admin/index.php
git commit -m "feat(admin): rich dashboard with stat trends, alerts, and Chart.js graphs"
```

---

## Task 5: Products page — search, stock, badges, icon actions

**Files:**
- Modify: `admin/products.php` (full rewrite)

- [ ] **Step 1: Rewrite `admin/products.php`**

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action');
    if ($action === 'toggle' && post('id')) {
        db()->prepare('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')
            ->execute([(int) post('id')]);
    }
    if ($action === 'delete' && post('id')) {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([(int) post('id')]);
    }
    header('Location: products.php');
    exit;
}

$q = trim((string) get('q', ''));
$sql = 'SELECT p.*, c.name AS category_name, '
     . '(SELECT COALESCE(SUM(stock),0) FROM product_variants v WHERE v.product_id = p.id) AS stock_total '
     . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE p.name LIKE ? OR c.name LIKE ?';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sql .= ' ORDER BY p.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
  <h1 class="font-display text-4xl">Products</h1>
  <a href="product-edit.php" class="flex items-center gap-2 rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm">
    <?= admin_icon('plus') ?> Add product
  </a>
</div>

<form method="get" class="mb-6 max-w-md relative">
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
  <input name="q" value="<?= e($q) ?>" placeholder="Search products…"
    class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[760px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Category</th>
        <th class="px-4 py-3">Price (GBP)</th>
        <th class="px-4 py-3">Stock</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <?php $stock = (int) $p['stock_total']; ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3"><?= e($p['name']) ?></td>
          <td class="px-4 py-3"><?= e($p['category_name'] ?? '—') ?></td>
          <td class="px-4 py-3">&pound;<?= number_format((float) $p['base_price'], 2) ?></td>
          <td class="px-4 py-3">
            <span class="<?= $stock <= 5 ? 'text-rose-600 font-semibold' : 'text-stone-700' ?>"><?= $stock ?></span>
            <?php if ($stock <= 5): ?><?= admin_icon('alert-triangle', 'w-3.5 h-3.5 inline text-rose-500') ?><?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($p['is_active']): ?>
              <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-100 text-emerald-800">Active</span>
            <?php else: ?>
              <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-stone-200 text-stone-600">Hidden</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-3">
              <a class="text-stone-500 hover:text-stone-900" title="Edit" href="product-edit.php?id=<?= (int) $p['id'] ?>"><?= admin_icon('pencil') ?></a>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="text-stone-500 hover:text-stone-900" title="Toggle active"><?= admin_icon('eye') ?></button></form>
              <form method="post" class="inline" onsubmit="return confirm('Delete product?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="text-rose-500 hover:text-rose-700" title="Delete"><?= admin_icon('trash-2') ?></button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400"><?= $q !== '' ? 'No products match your search' : 'No products yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/products.php`
Expected: `No syntax errors detected in admin/products.php`

- [ ] **Step 3: Verify render + behavior**

Load `http://localhost:8080/admin/products.php`. Expected: search box; Stock column shows summed variant stock with low-stock (≤5) in red + warning icon; Active/Hidden badges; edit/toggle/delete as icon buttons. Type a product name in search and submit → list filters. Click the eye (toggle) → status flips. Confirm no PHP warnings.

- [ ] **Step 4: Commit**

```bash
git add admin/products.php
git commit -m "feat(admin): product search, stock column, badges, icon actions"
```

---

## Task 6: Orders page — search, status filter, badges, mark shipped

**Files:**
- Modify: `admin/orders.php` (full rewrite)

- [ ] **Step 1: Rewrite `admin/orders.php`**

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    if (post('action') === 'ship' && post('id')) {
        db()->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")->execute([(int) post('id')]);
    }
    $qs = [];
    if (($r = trim((string) post('return_q'))) !== '') { $qs['q'] = $r; }
    if (($rs = trim((string) post('return_status'))) !== '') { $qs['status'] = $rs; }
    header('Location: orders.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

$statuses = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];
$q = trim((string) get('q', ''));
$status = (string) get('status', '');
$status = in_array($status, $statuses, true) ? $status : '';

$sql = 'SELECT * FROM orders';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-8">Orders</h1>

<form method="get" class="mb-6 flex flex-wrap gap-3 items-center">
  <div class="relative flex-1 min-w-[220px] max-w-md">
    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
    <input name="q" value="<?= e($q) ?>" placeholder="Search order # or email…"
      class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  </div>
  <select name="status" onchange="this.form.submit()" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm">Filter</button>
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[820px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Order</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Payment</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Total</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3"><a class="underline" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['order_number']) ?></a></td>
          <td class="px-4 py-3"><?= e($o['email']) ?></td>
          <td class="px-4 py-3"><?= e((string) $o['payment_method']) ?></td>
          <td class="px-4 py-3"><?= admin_status_badge((string) $o['status']) ?></td>
          <td class="px-4 py-3"><?= e($o['currency'] . ' ' . number_format((float) $o['total'], 2)) ?></td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-3">
              <a class="flex items-center gap-1 text-stone-500 hover:text-stone-900" href="order.php?id=<?= (int) $o['id'] ?>"><?= admin_icon('eye') ?> View</a>
              <?php if (!in_array($o['status'], ['shipped','delivered','cancelled','refunded'], true)): ?>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="ship">
                  <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                  <input type="hidden" name="return_q" value="<?= e($q) ?>">
                  <input type="hidden" name="return_status" value="<?= e($status) ?>">
                  <button class="flex items-center gap-1 text-indigo-600 hover:text-indigo-800" title="Mark shipped"><?= admin_icon('truck') ?> Ship</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">No orders match your filters</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/orders.php`
Expected: `No syntax errors detected in admin/orders.php`

- [ ] **Step 3: Verify render + behavior**

Load `http://localhost:8080/admin/orders.php`. Expected: search + status dropdown + Filter button; status badges; each non-final order shows a "Ship" quick action. Select a status → list filters (URL gains `?status=`). Click "Ship" on a pending order → it becomes `shipped` and the filter/search you had is preserved. Confirm no PHP warnings.

- [ ] **Step 4: Commit**

```bash
git add admin/orders.php
git commit -m "feat(admin): order search, status filter, badges, mark-shipped action"
```

---

## Task 7: Customers page — search, order count, total spent

**Files:**
- Modify: `admin/customers.php` (full rewrite)

- [ ] **Step 1: Rewrite `admin/customers.php`**

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$q = trim((string) get('q', ''));
$sql = "SELECT u.id, u.name, u.email, u.loyalty_points, u.created_at, "
     . "(SELECT COUNT(*) FROM orders o WHERE o.email = u.email) AS order_count, "
     . "(SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.email = u.email AND o.status IN ('paid','processing','shipped','delivered')) AS total_spent "
     . "FROM users u WHERE u.role = 'customer'";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sql .= ' ORDER BY u.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-8">Customers</h1>

<form method="get" class="mb-6 max-w-md relative">
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
  <input name="q" value="<?= e($q) ?>" placeholder="Search name or email…"
    class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[720px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Orders</th>
        <th class="px-4 py-3">Total spent (GBP)</th>
        <th class="px-4 py-3">Points</th>
        <th class="px-4 py-3">Joined</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
          <td class="px-4 py-3">
            <a class="underline text-stone-600" href="orders.php?q=<?= urlencode((string) $c['email']) ?>"><?= e($c['email']) ?></a>
          </td>
          <td class="px-4 py-3"><?= (int) $c['order_count'] ?></td>
          <td class="px-4 py-3">&pound;<?= number_format((float) $c['total_spent'], 2) ?></td>
          <td class="px-4 py-3"><?= (int) $c['loyalty_points'] ?></td>
          <td class="px-4 py-3 text-stone-500"><?= e($c['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400"><?= $q !== '' ? 'No customers match your search' : 'No customers yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/customers.php`
Expected: `No syntax errors detected in admin/customers.php`

- [ ] **Step 3: Verify render + behavior**

Load `http://localhost:8080/admin/customers.php`. Expected: search box; Orders count and Total spent columns computed per customer; clicking an email jumps to Orders filtered by that email. Search by name/email filters the list. Confirm no PHP warnings.

- [ ] **Step 4: Commit**

```bash
git add admin/customers.php
git commit -m "feat(admin): customer search with order count and total spent"
```

---

## Task 8: Combined global search page

**Files:**
- Create: `admin/search.php`

The top-bar search (from Task 3) submits to `search.php?q=`. This page queries orders, products, and customers and links into the respective pages.

- [ ] **Step 1: Create `admin/search.php`**

```php
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
```

- [ ] **Step 2: Verify syntax**

Run: `php -l admin/search.php`
Expected: `No syntax errors detected in admin/search.php`

- [ ] **Step 3: Verify render + behavior**

From any admin page, type a known product name or order email in the top search bar and submit. Expected: `search.php?q=...` lists matching Orders, Products, and Customers in three sections with working links; empty sections show "No matching …"; the results count in the subheading is correct. Confirm no PHP warnings.

- [ ] **Step 4: Commit**

```bash
git add admin/search.php
git commit -m "feat(admin): combined global search across orders, products, customers"
```

---

## Final verification

- [ ] Load every admin page (`index.php`, `products.php`, `orders.php`, `customers.php`, `search.php`) and confirm: icons render, no PHP warnings in the server terminal, no JS errors in the browser console.
- [ ] Confirm the active sidebar item is highlighted on each page.
- [ ] Confirm charts render on the dashboard and respond to the 7/30/90 date range.
- [ ] Confirm quick actions (product toggle/delete, order ship) work and preserve filters.

---

## Self-Review Notes

- **Spec coverage:** hybrid theme (Task 3), Lucide icons (Tasks 1,3–8), Chart.js graphs — revenue line, status doughnut, top products bar, payment doughnut (Task 4), stat cards + trends (Task 4), low-stock + pending alerts (Tasks 2,4), date-range selector (Tasks 2,4), status badges (Tasks 1,4,6), product/order/customer search (Tasks 5–7), combined search (Task 8), quick actions mark-shipped + toggle active (Tasks 5,6). All spec items mapped.
- **Portability:** all metrics aggregate in PHP over simple SELECTs; date filtering uses `substr(created_at,0,10)` string compare — works on SQLite and MySQL. No new tables.
- **Security:** all user input parameterized; quick actions CSRF-protected via existing `verify_csrf()`.
- **Naming consistency:** helper names (`admin_status_badge`, `admin_active_nav`, `admin_icon`, `metrics_*`) used consistently across tasks.
