<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$q = trim((string) get('q', ''));
$contacts = email_unified_contacts($q !== '' ? $q : null);

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="email-contacts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Sources', 'Orders'], ',', '"', '\\');
    foreach ($contacts as $c) {
        fputcsv($out, [$c['name'], $c['email'], $c['sources'], $c['orders']], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-4xl">Contacts</h1>
    <p class="text-sm text-stone-500 mt-1">Unified emails from subscribers, registered users, and orders.</p>
  </div>
  <div class="flex gap-2">
    <a href="email.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Back</a>
    <a href="email-contacts.php?export=csv<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 flex items-center gap-2"><?= admin_icon('download', 'w-4 h-4') ?> Export</a>
    <a href="email-compose.php" class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800">Compose</a>
  </div>
</div>

<form method="get" class="mb-6 relative max-w-md">
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
  <input name="q" value="<?= e($q) ?>" placeholder="Search name or email…" class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
</form>

<p class="text-sm text-stone-500 mb-3"><?= number_format(count($contacts)) ?> contact<?= count($contacts) === 1 ? '' : 's' ?></p>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[700px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Sources</th>
        <th class="px-4 py-3">Orders</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($contacts as $c): ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
          <td class="px-4 py-3"><?= e($c['email']) ?></td>
          <td class="px-4 py-3 capitalize text-stone-600"><?= e($c['sources']) ?></td>
          <td class="px-4 py-3"><?= (int) $c['orders'] ?></td>
          <td class="px-4 py-3 text-right">
            <a href="email-compose.php?custom=<?= urlencode($c['email']) ?>" class="text-sm text-stone-500 hover:text-stone-900">Email</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$contacts): ?>
        <tr><td colspan="5" class="px-4 py-10 text-center text-stone-400"><?= $q !== '' ? 'No contacts match your search' : 'No email contacts yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
