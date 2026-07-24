<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$campaigns = db()->query('SELECT * FROM email_campaigns ORDER BY id DESC LIMIT 200')->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-4xl">Campaigns</h1>
    <p class="text-sm text-stone-500 mt-1">History of every email marketing send.</p>
  </div>
  <div class="flex gap-2">
    <a href="email.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Dashboard</a>
    <a href="email-compose.php" class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800">New campaign</a>
  </div>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[760px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Subject</th>
        <th class="px-4 py-3">Type</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Recipients</th>
        <th class="px-4 py-3">Sent</th>
        <th class="px-4 py-3">Failed</th>
        <th class="px-4 py-3">Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3"><a class="font-medium hover:underline" href="email-campaign.php?id=<?= (int) $c['id'] ?>"><?= e($c['subject']) ?></a></td>
          <td class="px-4 py-3 capitalize text-stone-600"><?= e(str_replace('_', ' ', (string) $c['template_type'])) ?></td>
          <td class="px-4 py-3 capitalize"><?= e($c['status']) ?></td>
          <td class="px-4 py-3"><?= (int) $c['recipient_count'] ?></td>
          <td class="px-4 py-3 text-emerald-700"><?= (int) $c['sent_count'] ?></td>
          <td class="px-4 py-3 text-rose-600"><?= (int) $c['failed_count'] ?></td>
          <td class="px-4 py-3 text-stone-500 whitespace-nowrap"><?= e($c['sent_at'] ?: $c['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$campaigns): ?>
        <tr><td colspan="7" class="px-4 py-10 text-center text-stone-400">No campaigns yet</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
