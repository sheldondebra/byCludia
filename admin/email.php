<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$stats = email_contact_stats();
$recent = db()->query('SELECT * FROM email_campaigns ORDER BY id DESC LIMIT 8')->fetchAll();
$mailOk = mailer_enabled();

require __DIR__ . '/_layout_top.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
  <div>
    <h1 class="font-display text-4xl">Email Marketing</h1>
    <p class="text-sm text-stone-500 mt-1">Compose classy campaigns for subscribers, customers, and orders.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="email-contacts.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 flex items-center gap-2"><?= admin_icon('users', 'w-4 h-4') ?> Contacts</a>
    <a href="email-campaigns.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 flex items-center gap-2"><?= admin_icon('history', 'w-4 h-4') ?> History</a>
    <a href="email-compose.php" class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800 flex items-center gap-2"><?= admin_icon('pen-line', 'w-4 h-4') ?> New campaign</a>
  </div>
</div>

<?php if (!$mailOk): ?>
  <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 flex items-start gap-3">
    <?= admin_icon('alert-triangle', 'w-5 h-5 shrink-0 mt-0.5') ?>
    <div>
      <p class="font-medium">Email sending is disabled</p>
      <p class="mt-1 text-amber-800/80">Enable SMTP under <a href="integrations.php" class="underline">Integrations</a> before sending campaigns.</p>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
  <?php
  $cards = [
      ['Contacts', (string) $stats['contacts'], 'users'],
      ['Campaigns', (string) $stats['campaigns'], 'mail'],
      ['Emails sent', number_format($stats['emails_sent']), 'send'],
      ['Unsubscribed', (string) $stats['unsubscribed'], 'user-x'],
  ];
  foreach ($cards as $card): ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <div class="flex items-center justify-between">
        <p class="text-xs tracking-widest uppercase text-stone-400"><?= e($card[0]) ?></p>
        <span class="rounded-lg bg-[#F3C4C4]/40 text-stone-700 p-2"><?= admin_icon($card[2]) ?></span>
      </div>
      <p class="text-3xl font-display mt-3"><?= $card[1] ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-10">
  <a href="email-compose.php?template=failed_order&sources=orders&order_status=pending" class="bg-white rounded-2xl border border-stone-200 p-5 hover:border-stone-400 transition">
    <p class="text-xs uppercase tracking-widest text-stone-400 mb-2">Quick send</p>
    <h2 class="font-display text-2xl mb-1">Failed orders</h2>
    <p class="text-sm text-stone-500">Nudge pending checkouts — choose recipients in compose.</p>
  </a>
  <a href="email-compose.php?template=coupon" class="bg-white rounded-2xl border border-stone-200 p-5 hover:border-stone-400 transition">
    <p class="text-xs uppercase tracking-widest text-stone-400 mb-2">Quick send</p>
    <h2 class="font-display text-2xl mb-1">Coupon email</h2>
    <p class="text-sm text-stone-500">Attach an active coupon code in a classy layout.</p>
  </a>
  <a href="email-compose.php?template=holiday" class="bg-white rounded-2xl border border-stone-200 p-5 hover:border-stone-400 transition">
    <p class="text-xs uppercase tracking-widest text-stone-400 mb-2">Quick send</p>
    <h2 class="font-display text-2xl mb-1">Holiday email</h2>
    <p class="text-sm text-stone-500">Seasonal blast with hero image and gift CTA.</p>
  </a>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
  <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
    <h2 class="font-display text-2xl">Recent campaigns</h2>
    <a href="email-campaigns.php" class="text-sm text-stone-500 hover:text-stone-900">View all</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[640px]">
      <thead class="bg-stone-50 text-left text-stone-500">
        <tr>
          <th class="px-4 py-3">Subject</th>
          <th class="px-4 py-3">Type</th>
          <th class="px-4 py-3">Recipients</th>
          <th class="px-4 py-3">Sent / Failed</th>
          <th class="px-4 py-3">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $c): ?>
          <tr class="border-t border-stone-100 hover:bg-stone-50/60">
            <td class="px-4 py-3"><a class="font-medium hover:underline" href="email-campaign.php?id=<?= (int) $c['id'] ?>"><?= e($c['subject']) ?></a></td>
            <td class="px-4 py-3 capitalize text-stone-600"><?= e(str_replace('_', ' ', (string) $c['template_type'])) ?></td>
            <td class="px-4 py-3"><?= (int) $c['recipient_count'] ?></td>
            <td class="px-4 py-3"><span class="text-emerald-700"><?= (int) $c['sent_count'] ?></span> / <span class="text-rose-600"><?= (int) $c['failed_count'] ?></span></td>
            <td class="px-4 py-3 text-stone-500"><?= e($c['sent_at'] ?: $c['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="px-4 py-10 text-center text-stone-400">No campaigns yet — create your first one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
