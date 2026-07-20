<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token')) && post('action') === 'delete') {
    db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int) post('id')]);
    flash('success', 'Subscriber removed.');
    header('Location: subscribers.php');
    exit;
}

$q = trim((string) get('q', ''));

// CSV export
if (get('export') === 'csv') {
    $rows = $q !== ''
        ? (function () use ($q) { $s = db()->prepare('SELECT * FROM subscribers WHERE phone LIKE ? OR email LIKE ? OR name LIKE ? ORDER BY id DESC'); $s->execute(["%$q%", "%$q%", "%$q%"]); return $s->fetchAll(); })()
        : db()->query('SELECT * FROM subscribers ORDER BY id DESC')->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Phone', 'Email', 'Source', 'Subscribed'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['name'], $r['phone'], $r['email'], $r['source'], $r['created_at']], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

if ($q !== '') {
    $stmt = db()->prepare('SELECT * FROM subscribers WHERE phone LIKE ? OR email LIKE ? OR name LIKE ? ORDER BY id DESC');
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $subs = $stmt->fetchAll();
} else {
    $subs = db()->query('SELECT * FROM subscribers ORDER BY id DESC')->fetchAll();
}

$total = (int) db()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$today = (int) db()->query("SELECT COUNT(*) FROM subscribers WHERE date(created_at) = date('now')")->fetchColumn();
$withEmail = (int) db()->query("SELECT COUNT(*) FROM subscribers WHERE email IS NOT NULL AND email <> ''")->fetchColumn();

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-4xl">Subscribers</h1>
    <p class="text-sm text-stone-500 mt-1">Marketing signups from the popup and newsletter.</p>
  </div>
  <a href="subscribers.php?export=csv<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 flex items-center gap-2"><?= admin_icon('download', 'w-4 h-4') ?> Export CSV</a>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-6 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Total</p><p class="text-2xl font-semibold"><?= $total ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Today</p><p class="text-2xl font-semibold"><?= $today ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">With email</p><p class="text-2xl font-semibold"><?= $withEmail ?></p></div>
</div>

<form method="get" class="mb-6 relative max-w-md">
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
  <input name="q" value="<?= e($q) ?>" placeholder="Search name, phone or email…" class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[700px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Phone</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Source</th>
        <th class="px-4 py-3">Subscribed</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subs as $s): ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3"><?= e((string) ($s['name'] ?: '—')) ?></td>
          <td class="px-4 py-3 font-mono text-xs"><a href="tel:<?= e((string) $s['phone']) ?>" class="hover:underline"><?= e((string) $s['phone']) ?></a></td>
          <td class="px-4 py-3"><?= e((string) ($s['email'] ?: '—')) ?></td>
          <td class="px-4 py-3 capitalize text-stone-500"><?= e((string) $s['source']) ?></td>
          <td class="px-4 py-3 text-stone-500 whitespace-nowrap"><?= e(date('d M Y', strtotime((string) $s['created_at']))) ?></td>
          <td class="px-4 py-3 text-right">
            <form method="post" onsubmit="return confirm('Remove this subscriber?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="text-rose-500 hover:text-rose-700"><?= admin_icon('trash-2', 'w-4 h-4') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$subs): ?><tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">No subscribers yet</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
