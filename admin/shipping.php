<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

function shipping_admin_save_setting(string $key, string $val): void
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        )->execute([$key, $val]);
    } else {
        db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $val]);
    }
}

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = (string) post('action');
    $id = (int) post('id');
    $result = ['ok' => true, 'saved' => 0];

    if ($action === 'save_defaults') {
        $flat = max(0, (float) post('shipping_flat'));
        $threshold = max(0, (float) post('free_shipping_threshold'));
        shipping_admin_save_setting('shipping_flat', number_format($flat, 2, '.', ''));
        shipping_admin_save_setting('free_shipping_threshold', $threshold > 0 ? number_format($threshold, 2, '.', '') : '');
        flash('success', 'Default shipping settings saved.');
    } elseif ($action === 'delete' && $id) {
        shipping_country_rate_delete($id);
        flash('success', 'Country override deleted.');
    } elseif ($action === 'toggle' && $id) {
        shipping_country_rate_toggle($id);
        flash('success', 'Country override updated.');
    } elseif ($action === 'save') {
        $codes = post('country_codes');
        if (!is_array($codes)) {
            $codes = [];
        }
        // Fallback for single edit payloads
        $single = trim((string) post('country_code', ''));
        if ($single !== '') {
            $codes[] = $single;
        }

        $result = shipping_country_rate_save_many(
            $codes,
            (float) post('rate'),
            (bool) post('is_active'),
            (string) post('label', '')
        );
        if ($result['ok']) {
            $n = (int) ($result['saved'] ?? 0);
            flash('success', $n === 1 ? 'Shipping override saved.' : $n . ' country overrides saved.');
        } else {
            flash('error', $result['error'] ?? 'Could not save overrides.');
        }
    }

    header('Location: shipping.php');
    exit;
}

$editId = (int) get('edit', 0);
$editing = $editId ? shipping_country_rate_find($editId) : null;
$rates = shipping_country_rates_all();
$countries = geo_countries();
$checkedCodes = [];
if ($editing) {
    $checkedCodes[] = strtoupper((string) $editing['country_code']);
}
$existingByCode = [];
foreach ($rates as $row) {
    $existingByCode[strtoupper((string) $row['country_code'])] = $row;
}

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-2">Shipping</h1>
<p class="text-sm text-stone-500 mb-6">Set a default rate for all countries, then group countries that share the same fee.</p>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-4 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-2 gap-6 items-start mb-6">
  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_defaults">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('truck', 'w-4 h-4 text-stone-400') ?> Defaults</h2>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Default shipping rate (GBP)</label>
      <input name="shipping_flat" type="number" step="0.01" min="0" required
        value="<?= e((string) setting('shipping_flat', '15')) ?>"
        class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      <p class="text-[11px] text-stone-400 mt-1">Used for every country without an active override (including “Other”).</p>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Free shipping over (GBP)</label>
      <input name="free_shipping_threshold" type="number" step="0.01" min="0"
        value="<?= e((string) setting('free_shipping_threshold', '')) ?>"
        placeholder="0 = disabled"
        class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      <p class="text-[11px] text-stone-400 mt-1">When the cart reaches this amount, shipping is free for all countries.</p>
    </div>
    <button class="rounded-full bg-stone-900 text-white px-6 py-2.5 text-sm hover:bg-stone-800 transition">Save defaults</button>
  </form>

  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4" data-shipping-group-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <h2 class="font-medium"><?= $editing ? 'Edit / group shipping fee' : 'Group shipping fee' ?></h2>
    <p class="text-xs text-stone-500">Check one or more countries, set a rate, and save — all selected countries get the same fee.</p>

    <div>
      <div class="flex items-center justify-between gap-2 mb-2">
        <label class="text-xs text-stone-500" for="country-filter">Countries</label>
        <span class="text-[11px] text-stone-400" data-country-count>0 selected</span>
      </div>
      <input id="country-filter" type="search" placeholder="Search and find countries…"
        class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"
        data-country-filter autocomplete="off">
      <div class="flex flex-wrap gap-2 mb-2">
        <button type="button" class="text-xs underline text-stone-500 hover:text-stone-800" data-country-select-visible>Select visible</button>
        <button type="button" class="text-xs underline text-stone-500 hover:text-stone-800" data-country-clear>Clear</button>
      </div>
      <style>
        .ship-check {
          width: 1.15rem;
          height: 1.15rem;
          border-radius: 9999px;
          border: 1.5px solid #d6d3d1;
          background: #fff;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
        }
        .ship-check svg {
          width: 0.7rem;
          height: 0.7rem;
          opacity: 0;
          transform: scale(0.7);
          transition: opacity .12s ease, transform .12s ease;
          color: #fff;
        }
        .peer:checked + .ship-check {
          background: #1c1917;
          border-color: #1c1917;
          box-shadow: 0 0 0 3px rgba(243, 196, 196, 0.45);
        }
        .peer:checked + .ship-check svg { opacity: 1; transform: scale(1); }
        .peer:focus-visible + .ship-check {
          box-shadow: 0 0 0 3px rgba(243, 196, 196, 0.7);
        }
        .ship-country-row.is-filtered-out { display: none !important; }
      </style>
      <div class="max-h-64 overflow-y-auto rounded-xl border border-stone-200 divide-y divide-stone-100" data-country-checklist>
        <?php foreach ($countries as $c):
          $code = $c['code'];
          $isChecked = in_array($code, $checkedCodes, true);
          $hasRate = isset($existingByCode[$code]);
          $search = strtolower($c['name'] . ' ' . $code);
        ?>
          <label class="ship-country-row flex items-center gap-3 px-3 py-2.5 text-sm hover:bg-stone-50 cursor-pointer" data-country-row data-search="<?= e($search) ?>">
            <input type="checkbox" name="country_codes[]" value="<?= e($code) ?>"
              class="peer sr-only" data-country-check
              <?= $isChecked ? 'checked' : '' ?>>
            <span class="ship-check" aria-hidden="true">
              <svg fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="flex-1 min-w-0">
              <span class="font-medium"><?= e($c['name']) ?></span>
              <span class="text-stone-400">· <?= e($code) ?></span>
            </span>
            <?php if ($hasRate): ?>
              <span class="text-[11px] text-stone-400 shrink-0">£<?= number_format((float) $existingByCode[$code]['rate'], 2) ?></span>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <p class="hidden px-3 py-6 text-center text-sm text-stone-400" data-country-empty>No countries match your search.</p>
      </div>
    </div>

    <div>
      <label class="text-xs text-stone-500 mb-1 block">Rate (GBP) for selected countries</label>
      <input name="rate" type="number" step="0.01" min="0" required
        value="<?= e((string) ($editing['rate'] ?? '')) ?>"
        placeholder="80.00"
        class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Group note (optional)</label>
      <input name="label" maxlength="190"
        value="<?= e((string) ($editing['label'] ?? '')) ?>"
        placeholder="e.g. Southern Africa / Remote"
        class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="is_active" value="1" <?= !$editing || !empty($editing['is_active']) ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4">
      Active
    </label>
    <div class="flex gap-2">
      <button class="flex-1 rounded-full bg-stone-900 text-white px-6 py-2.5 text-sm hover:bg-stone-800 transition">
        <?= $editing ? 'Save fee for selected' : 'Apply fee to selected' ?>
      </button>
      <?php if ($editing): ?>
        <a href="shipping.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[640px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Country</th>
        <th class="px-4 py-3">Rate</th>
        <th class="px-4 py-3">Note</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rates): ?>
        <tr><td colspan="5" class="px-4 py-8 text-center text-stone-400">No country overrides yet. All destinations use the default rate.</td></tr>
      <?php endif; ?>
      <?php foreach ($rates as $row): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3 font-medium">
            <?= e(geo_country_name((string) $row['country_code'])) ?>
            <span class="text-stone-400 font-normal">· <?= e((string) $row['country_code']) ?></span>
          </td>
          <td class="px-4 py-3">£<?= number_format((float) $row['rate'], 2) ?></td>
          <td class="px-4 py-3 text-stone-500"><?= e((string) ($row['label'] ?? '—')) ?></td>
          <td class="px-4 py-3">
            <?php if (!empty($row['is_active'])): ?>
              <span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">Active</span>
            <?php else: ?>
              <span class="inline-flex px-2 py-0.5 rounded-full bg-stone-100 text-stone-500 text-xs">Disabled</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="inline-flex items-center justify-end gap-1.5">
              <a href="shipping.php?edit=<?= (int) $row['id'] ?>"
                class="inline-flex items-center justify-center w-8 h-8 rounded-full text-stone-500 hover:text-stone-900 hover:bg-stone-100 transition"
                title="Edit"><?= admin_icon('pencil', 'w-4 h-4') ?></a>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <?php if (!empty($row['is_active'])): ?>
                  <button type="submit"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full text-stone-500 hover:text-rose-600 hover:bg-rose-50 transition"
                    title="Disable"><?= admin_icon('x', 'w-4 h-4') ?></button>
                <?php else: ?>
                  <button type="submit"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full text-stone-500 hover:text-emerald-600 hover:bg-emerald-50 transition"
                    title="Enable / Active"><?= admin_icon('check', 'w-4 h-4') ?></button>
                <?php endif; ?>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete this override?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button type="submit"
                  class="inline-flex items-center justify-center w-8 h-8 rounded-full text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition"
                  title="Delete"><?= admin_icon('trash-2', 'w-4 h-4') ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<p class="mt-4 text-xs text-stone-400">
  Carrier labels (DHL / FedEx) in Settings are for fulfilment notes only — checkout pricing uses the default rate or country overrides above.
</p>

<script>
(() => {
  const form = document.querySelector('[data-shipping-group-form]');
  if (!form) return;
  const filter = form.querySelector('[data-country-filter]');
  const countEl = form.querySelector('[data-country-count]');
  const emptyEl = form.querySelector('[data-country-empty]');
  const rows = Array.from(form.querySelectorAll('[data-country-row]'));
  const checks = () => Array.from(form.querySelectorAll('[data-country-check]'));
  const isVisible = (row) => !row.classList.contains('is-filtered-out');

  const syncCount = () => {
    const n = checks().filter((c) => c.checked).length;
    if (countEl) countEl.textContent = n === 1 ? '1 selected' : n + ' selected';
  };

  const applyFilter = () => {
    const q = (filter && filter.value || '').trim().toLowerCase();
    let visible = 0;
    rows.forEach((row) => {
      const hay = (row.getAttribute('data-search') || '') + ' ' + (row.textContent || '').toLowerCase();
      const match = q === '' || hay.includes(q);
      row.classList.toggle('is-filtered-out', !match);
      if (match) visible += 1;
    });
    if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0 || q === '');
  };

  form.querySelector('[data-country-select-visible]')?.addEventListener('click', () => {
    rows.forEach((row) => {
      if (!isVisible(row)) return;
      const box = row.querySelector('[data-country-check]');
      if (box) box.checked = true;
    });
    syncCount();
  });

  form.querySelector('[data-country-clear]')?.addEventListener('click', () => {
    checks().forEach((c) => { c.checked = false; });
    syncCount();
  });

  filter?.addEventListener('input', applyFilter);
  filter?.addEventListener('search', applyFilter);
  filter?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') e.preventDefault();
  });

  form.addEventListener('change', (e) => {
    if (e.target && e.target.matches('[data-country-check]')) syncCount();
  });

  form.addEventListener('submit', (e) => {
    if (checks().filter((c) => c.checked).length === 0) {
      e.preventDefault();
      alert('Select at least one country.');
    }
  });

  applyFilter();
  syncCount();
})();
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
