<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = (string) post('action');
    if ($action === 'delete') {
        db()->prepare('DELETE FROM gift_cards WHERE id = ?')->execute([(int) post('id')]);
        flash('success', 'Gift card deleted.');
    } elseif ($action === 'toggle') {
        $id = (int) post('id');
        $cur = db()->prepare('SELECT status FROM gift_cards WHERE id = ?');
        $cur->execute([$id]);
        $status = (string) $cur->fetchColumn();
        $new = $status === 'disabled' ? 'active' : 'disabled';
        db()->prepare('UPDATE gift_cards SET status = ? WHERE id = ?')->execute([$new, $id]);
        flash('success', 'Gift card ' . ($new === 'disabled' ? 'disabled' : 're-activated') . '.');
    } elseif ($action === 'create') {
        $amount = round((float) post('amount'), 2);
        $email = trim((string) post('recipient_email'));
        if ($amount <= 0) {
            flash('error', 'Enter a valid amount.');
        } else {
            $code = gift_generate_code();
            db()->prepare(
                'INSERT INTO gift_cards (code, initial_amount, balance, currency, recipient_name, recipient_email, sender_name, message, purchaser_email, status)
                 VALUES (?, ?, ?, "GBP", ?, ?, ?, ?, ?, "active")'
            )->execute([
                $code, $amount, $amount,
                trim((string) post('recipient_name')) ?: null,
                $email ?: null,
                'By Claudia Darlene',
                trim((string) post('message')) ?: null,
                'admin',
            ]);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                gift_card_send_email($code, $amount, [
                    'gift_recipient_name' => post('recipient_name'),
                    'gift_recipient_email' => $email,
                    'gift_sender_name' => 'By Claudia Darlene',
                    'gift_message' => post('message'),
                ]);
            }
            flash('success', 'Gift card ' . $code . ' created.');
        }
    }
    header('Location: gift-cards.php');
    exit;
}

$q = trim((string) get('q', ''));
if ($q !== '') {
    $stmt = db()->prepare('SELECT * FROM gift_cards WHERE code LIKE ? OR recipient_email LIKE ? OR purchaser_email LIKE ? ORDER BY id DESC');
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $cards = $stmt->fetchAll();
} else {
    $cards = db()->query('SELECT * FROM gift_cards ORDER BY id DESC')->fetchAll();
}

$totalIssued = (int) db()->query('SELECT COUNT(*) FROM gift_cards')->fetchColumn();
$totalValue = (float) db()->query('SELECT COALESCE(SUM(initial_amount),0) FROM gift_cards')->fetchColumn();
$outstanding = (float) db()->query("SELECT COALESCE(SUM(balance),0) FROM gift_cards WHERE status='active'")->fetchColumn();
$activeCount = (int) db()->query("SELECT COUNT(*) FROM gift_cards WHERE status='active' AND balance > 0")->fetchColumn();

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-4xl">Gift cards</h1>
    <p class="text-sm text-stone-500 mt-1">Issued, purchased and redeemable gift cards.</p>
  </div>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-6 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-6 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Issued</p><p class="text-2xl font-semibold"><?= $totalIssued ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Total value</p><p class="text-2xl font-semibold">&pound;<?= number_format($totalValue, 2) ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Outstanding balance</p><p class="text-2xl font-semibold">&pound;<?= number_format($outstanding, 2) ?></p></div>
  <div class="bg-white rounded-2xl border border-stone-200 p-4"><p class="text-xs text-stone-500">Active</p><p class="text-2xl font-semibold"><?= $activeCount ?></p></div>
</div>

<div class="grid lg:grid-cols-[1fr_320px] gap-6 items-start">
  <div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto order-2 lg:order-1">
    <form method="get" class="p-4 border-b border-stone-100 relative">
      <span class="absolute left-8 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
      <input name="q" value="<?= e($q) ?>" placeholder="Search code or email…" class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </form>
    <table class="w-full text-sm min-w-[760px]">
      <thead class="bg-stone-50 text-left text-stone-500">
        <tr>
          <th class="px-4 py-3">Code</th>
          <th class="px-4 py-3">Balance</th>
          <th class="px-4 py-3">Recipient</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Created</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cards as $c): ?>
          <tr class="border-t border-stone-100 hover:bg-stone-50/60">
            <td class="px-4 py-3 font-mono text-xs"><?= e((string) $c['code']) ?></td>
            <td class="px-4 py-3 whitespace-nowrap">&pound;<?= number_format((float) $c['balance'], 2) ?> <span class="text-stone-400 text-xs">/ &pound;<?= number_format((float) $c['initial_amount'], 2) ?></span></td>
            <td class="px-4 py-3"><?= e((string) ($c['recipient_email'] ?: '—')) ?></td>
            <td class="px-4 py-3">
              <?php
                $badge = ['active' => 'bg-emerald-100 text-emerald-700', 'redeemed' => 'bg-stone-200 text-stone-600', 'disabled' => 'bg-rose-100 text-rose-700'][$c['status']] ?? 'bg-stone-100 text-stone-600';
              ?>
              <span class="px-2.5 py-1 rounded-full text-xs <?= $badge ?>"><?= e((string) $c['status']) ?></span>
            </td>
            <td class="px-4 py-3 text-stone-500 whitespace-nowrap"><?= e(date('d M Y', strtotime((string) $c['created_at']))) ?></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <form method="post" class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="text-stone-500 hover:text-stone-900 text-xs underline mr-3"><?= $c['status'] === 'disabled' ? 'Enable' : 'Disable' ?></button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete this gift card permanently?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="text-rose-500 hover:text-rose-700"><?= admin_icon('trash-2', 'w-4 h-4') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$cards): ?><tr><td colspan="6" class="px-4 py-10 text-center text-stone-400">No gift cards yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4 order-1 lg:order-2">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('plus-circle', 'w-4 h-4 text-stone-400') ?> Issue a gift card</h2>
    <p class="text-xs text-stone-500">Manually create a card (e.g. for a promotion or refund). Emails the recipient if an address is given.</p>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Amount (GBP)</label>
        <input name="amount" type="number" step="0.01" min="1" required placeholder="50.00" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Recipient name</label>
        <input name="recipient_name" placeholder="Optional" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Recipient email</label>
        <input name="recipient_email" type="email" placeholder="Optional — sends the code" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Message</label>
        <textarea name="message" rows="2" placeholder="Optional" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"></textarea>
      </div>
      <button class="w-full rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800 transition">Create gift card</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
