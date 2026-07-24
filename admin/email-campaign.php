<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$id = (int) get('id', 0);
$stmt = db()->prepare('SELECT * FROM email_campaigns WHERE id = ?');
$stmt->execute([$id]);
$campaign = $stmt->fetch();
if (!$campaign) {
    flash('error', 'Campaign not found.');
    header('Location: email-campaigns.php');
    exit;
}

$recipients = db()->prepare('SELECT * FROM email_campaign_recipients WHERE campaign_id = ? ORDER BY id ASC');
$recipients->execute([$id]);
$recipients = $recipients->fetchAll();

$audience = json_decode((string) ($campaign['audience_json'] ?? ''), true) ?: [];

require __DIR__ . '/_layout_top.php';
?>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-3xl sm:text-4xl max-w-3xl"><?= e($campaign['subject']) ?></h1>
    <p class="text-sm text-stone-500 mt-1 capitalize">
      <?= e(str_replace('_', ' ', (string) $campaign['template_type'])) ?>
      &middot; <?= e($campaign['status']) ?>
      &middot; <?= e($campaign['sent_at'] ?: $campaign['created_at']) ?>
    </p>
  </div>
  <div class="flex gap-2">
    <a href="email-campaigns.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">All campaigns</a>
    <a href="email-compose.php?template=<?= urlencode((string) $campaign['template_type']) ?>" class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800">New similar</a>
  </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Recipients</p><p class="text-2xl font-semibold"><?= (int) $campaign['recipient_count'] ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Sent</p><p class="text-2xl font-semibold text-emerald-700"><?= (int) $campaign['sent_count'] ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Failed</p><p class="text-2xl font-semibold text-rose-600"><?= (int) $campaign['failed_count'] ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Coupon</p><p class="text-lg font-semibold truncate"><?= e($campaign['coupon_code'] ?: '—') ?></p></div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h2 class="font-medium mb-3">Audience</h2>
    <p class="text-sm text-stone-600">Sources: <?= e(implode(', ', $audience['sources'] ?? []) ?: '—') ?></p>
    <?php if (!empty($audience['order_statuses'])): ?>
      <p class="text-sm text-stone-600 mt-1">Order statuses: <?= e(implode(', ', $audience['order_statuses'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($audience['custom'])): ?>
      <p class="text-sm text-stone-600 mt-1">Included pasted emails</p>
    <?php endif; ?>
    <?php if ($campaign['headline']): ?>
      <p class="text-sm text-stone-500 mt-4">Headline: <?= e($campaign['headline']) ?></p>
    <?php endif; ?>
  </div>
  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-stone-100 text-sm font-medium">Email preview</div>
    <div class="bg-[#FBF7F2] p-3 max-h-[420px] overflow-auto">
      <iframe title="Campaign preview" class="w-full min-h-[380px] border-0" srcdoc="<?= e($campaign['body_html']) ?>"></iframe>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <div class="px-4 py-3 border-b border-stone-100 font-medium text-sm">Recipients</div>
  <table class="w-full text-sm min-w-[700px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Source</th>
        <th class="px-4 py-3">Order</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3">Error</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recipients as $r): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3"><?= e($r['email']) ?></td>
          <td class="px-4 py-3"><?= e($r['name'] ?? '') ?></td>
          <td class="px-4 py-3 capitalize text-stone-600"><?= e($r['source'] ?? '') ?></td>
          <td class="px-4 py-3 text-stone-500"><?= e($r['order_number'] ?? '—') ?></td>
          <td class="px-4 py-3">
            <?php if ($r['status'] === 'sent'): ?>
              <span class="text-emerald-700">Sent</span>
            <?php elseif ($r['status'] === 'failed'): ?>
              <span class="text-rose-600">Failed</span>
            <?php else: ?>
              <span class="text-stone-500"><?= e($r['status']) ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-xs text-rose-600 max-w-[220px] truncate"><?= e($r['error_message'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recipients): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">No recipients logged</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
