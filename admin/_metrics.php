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

/** Render a trend badge (up/down %). */
function metrics_trend_badge(?float $pct): string
{
    if ($pct === null) {
        return '<span class="text-xs text-stone-400">&mdash;</span>';
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
        'labels' => array_map(fn($k) => date('j M', (int) strtotime($k)), array_keys($buckets)),
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
