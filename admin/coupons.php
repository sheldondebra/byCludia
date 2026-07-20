<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
    } elseif ($action === 'toggle' && $id) {
        db()->prepare('UPDATE coupons SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
    } elseif ($action === 'save') {
        $code = strtoupper(trim((string) post('code')));
        $type = post('type') === 'fixed' ? 'fixed' : 'percent';
        $value = (float) post('value');
        $minOrder = post('min_order') !== '' ? (float) post('min_order') : null;
        $maxUses = post('max_uses') !== '' ? (int) post('max_uses') : null;
        $expires = trim((string) post('expires_at')) ?: null;
        $active = post('is_active') ? 1 : 0;
        if ($code !== '' && $value > 0) {
            if ($id) {
                db()->prepare('UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, expires_at=?, is_active=? WHERE id=?')
                    ->execute([$code, $type, $value, $minOrder, $maxUses, $expires, $active, $id]);
            } else {
                db()->prepare('INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at, is_active) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$code, $type, $value, $minOrder, $maxUses, $expires, $active]);
            }
            flash('success', 'Coupon saved.');
        } else {
            flash('error', 'Code and a value greater than 0 are required.');
        }
    }
    header('Location: coupons.php');
    exit;
}

$editId = (int) get('edit', 0);
$editing = null;
if ($editId) {
    $e = db()->prepare('SELECT * FROM coupons WHERE id = ?');
    $e->execute([$editId]);
    $editing = $e->fetch() ?: null;
}

$coupons = db()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-2">Coupons</h1>
<p class="text-sm text-stone-500 mb-6">Discount codes customers can apply at checkout.</p>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-4 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-[360px_1fr] gap-6 items-start">
  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="font-medium"><?= $editing ? 'Edit coupon' : 'New coupon' ?></h2>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Code</label>
      <input name="code" required value="<?= e($editing['code'] ?? '') ?>" placeholder="SUMMER10" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Type</label>
        <select name="type" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
          <option value="percent" <?= ($editing['type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent %</option>
          <option value="fixed" <?= ($editing['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed £</option>
        </select>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Value</label>
        <input name="value" type="number" step="0.01" required value="<?= e((string) ($editing['value'] ?? '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Min order (£)</label>
        <input name="min_order" type="number" step="0.01" value="<?= e((string) ($editing['min_order'] ?? '')) ?>" placeholder="Optional" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Max uses</label>
        <input name="max_uses" type="number" value="<?= e((string) ($editing['max_uses'] ?? '')) ?>" placeholder="Unlimited" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Expires</label>
      <input name="expires_at" type="date" value="<?= e($editing['expires_at'] ? substr((string) $editing['expires_at'], 0, 10) : '') ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !$editing || $editing['is_active'] ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4"> Active</label>
    <div class="flex gap-2">
      <button class="flex-1 rounded-full bg-stone-900 text-white px-6 py-2.5 text-sm hover:bg-stone-800 transition"><?= $editing ? 'Update' : 'Create' ?></button>
      <?php if ($editing): ?><a href="coupons.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Cancel</a><?php endif; ?>
    </div>
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm min-w-[640px]">
      <thead class="bg-stone-50 text-left text-stone-500">
        <tr>
          <th class="px-4 py-3">Code</th>
          <th class="px-4 py-3">Discount</th>
          <th class="px-4 py-3">Min</th>
          <th class="px-4 py-3">Uses</th>
          <th class="px-4 py-3">Expires</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coupons as $c): ?>
          <tr class="border-t border-stone-100">
            <td class="px-4 py-3 font-medium"><?= e($c['code']) ?></td>
            <td class="px-4 py-3"><?= $c['type'] === 'percent' ? (float) $c['value'] . '%' : '£' . number_format((float) $c['value'], 2) ?></td>
            <td class="px-4 py-3 text-stone-500"><?= $c['min_order'] ? '£' . number_format((float) $c['min_order'], 2) : '—' ?></td>
            <td class="px-4 py-3 text-stone-500"><?= (int) $c['used_count'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?></td>
            <td class="px-4 py-3 text-stone-500"><?= $c['expires_at'] ? e(date('d M Y', strtotime((string) $c['expires_at']))) : '—' ?></td>
            <td class="px-4 py-3">
              <?php if ($c['is_active']): ?><span class="rounded-full px-2.5 py-1 text-xs bg-emerald-100 text-emerald-800">Active</span><?php else: ?><span class="rounded-full px-2.5 py-1 text-xs bg-stone-200 text-stone-600">Off</span><?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-3">
                <a class="text-stone-500 hover:text-stone-900" href="coupons.php?edit=<?= (int) $c['id'] ?>" title="Edit"><?= admin_icon('pencil') ?></a>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="text-stone-500 hover:text-stone-900" title="Toggle"><?= admin_icon('power') ?></button></form>
                <form method="post" class="inline" onsubmit="return confirm('Delete coupon?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="text-rose-500 hover:text-rose-700" title="Delete"><?= admin_icon('trash-2') ?></button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$coupons): ?><tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">No coupons yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
