<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$provider = trim((string) get('provider', ''));
$status = trim((string) get('status', ''));
$q = trim((string) get('q', ''));

$where = [];
$params = [];
if ($provider !== '') { $where[] = 'p.provider = ?'; $params[] = $provider; }
if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(p.provider_ref LIKE ? OR o.order_number LIKE ? OR o.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$txns = db()->prepare(
    'SELECT p.*, o.order_number, o.email, o.status AS order_status FROM payments p '
    . 'LEFT JOIN orders o ON o.id = p.order_id' . $whereSql . ' ORDER BY p.id DESC LIMIT 500'
);
$txns->execute($params);
$rows = $txns->fetchAll();

// Stats
$captured = db()->query("SELECT COALESCE(SUM(amount),0) s, COUNT(*) c FROM payments WHERE status = 'succeeded'")->fetch();
$byProvider = [];
foreach (db()->query("SELECT provider, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments WHERE status='succeeded' GROUP BY provider") as $r) {
    $byProvider[$r['provider']] = ['c' => (int) $r['c'], 's' => (float) $r['s']];
}
$providers = [];
foreach (db()->query('SELECT DISTINCT provider FROM payments') as $r) { $providers[] = $r['provider']; }
$statuses = [];
foreach (db()->query('SELECT DISTINCT status FROM payments') as $r) { $statuses[] = $r['status']; }

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6">
  <h1 class="font-display text-4xl">Transactions</h1>
  <p class="text-sm text-stone-500 mt-1"><?= (int) $captured['c'] ?> successful payments captured</p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-2xl border border-stone-200 p-4">
    <p class="text-xs text-stone-500">Total captured</p>
    <p class="text-2xl font-semibold"><?= number_format((float) $captured['s'], 2) ?></p>
    <p class="text-xs text-stone-400 mt-1">across all currencies</p>
  </div>
  <?php foreach (['stripe' => 'Stripe', 'paystack' => 'Paystack', 'demo' => 'Demo'] as $pk => $pl): ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-4">
      <p class="text-xs text-stone-500"><?= $pl ?></p>
      <p class="text-2xl font-semibold"><?= number_format($byProvider[$pk]['s'] ?? 0, 2) ?></p>
      <p class="text-xs text-stone-400 mt-1"><?= (int) ($byProvider[$pk]['c'] ?? 0) ?> payments</p>
    </div>
  <?php endforeach; ?>
</div>

<form method="get" class="mb-6 flex flex-wrap gap-3">
  <div class="relative flex-1 min-w-[220px] max-w-md">
    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
    <input name="q" value="<?= e($q) ?>" placeholder="Reference, order # or email…" class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  </div>
  <select name="provider" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm">
    <option value="">All providers</option>
    <?php foreach ($providers as $pv): ?><option value="<?= e($pv) ?>" <?= $provider === $pv ? 'selected' : '' ?>><?= e(ucfirst($pv)) ?></option><?php endforeach; ?>
  </select>
  <select name="status" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $st): ?><option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
  </select>
  <button class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800 transition">Filter</button>
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[820px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Reference</th>
        <th class="px-4 py-3">Order</th>
        <th class="px-4 py-3">Provider</th>
        <th class="px-4 py-3">Amount</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $t): ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3 font-mono text-xs"><?= e((string) $t['provider_ref']) ?></td>
          <td class="px-4 py-3"><?php if ($t['order_id']): ?><a href="order.php?id=<?= (int) $t['order_id'] ?>" class="hover:underline"><?= e((string) $t['order_number']) ?></a><br><span class="text-xs text-stone-400"><?= e((string) $t['email']) ?></span><?php else: ?>—<?php endif; ?></td>
          <td class="px-4 py-3 capitalize"><?= e((string) $t['provider']) ?></td>
          <td class="px-4 py-3 font-medium tabular-nums"><?= e((string) $t['currency']) ?> <?= number_format((float) $t['amount'], 2) ?></td>
          <td class="px-4 py-3">
            <?php $c = $t['status'] === 'succeeded' ? 'emerald' : ($t['status'] === 'failed' ? 'rose' : 'amber'); ?>
            <span class="rounded-full px-2.5 py-1 text-xs bg-<?= $c ?>-100 text-<?= $c ?>-800"><?= e(ucfirst((string) $t['status'])) ?></span>
          </td>
          <td class="px-4 py-3 text-stone-500 whitespace-nowrap"><?= e(date('d M Y H:i', strtotime((string) $t['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">No transactions yet</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
