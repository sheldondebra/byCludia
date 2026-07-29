<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

function admin_setting_save(string $key, string $val): void
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')
            ->execute([$key, $val]);
    } else {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
            ->execute([$key, $val]);
    }
}

function admin_settings_upload(array $file): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'], true)) {
        return null;
    }
    $dir = ROOT_PATH . '/assets/images/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = 'logo-' . time() . '.' . $ext;
    $rel = 'assets/images/uploads/' . $name;
    return @move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $rel) ? $rel : null;
}

$saved = false;
if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $keys = [
        'store_name', 'meta_description', 'promo_banner', 'hero_title', 'hero_subtitle', 'about_blurb',
        'shipping_flat', 'free_shipping_threshold',
        'ship_dhl_label', 'ship_dhl_rate',
        'ship_fedex_label', 'ship_fedex_rate',
        'contact_phone', 'contact_email', 'contact_address',
        'social_instagram', 'social_tiktok', 'social_facebook',
        'popup_title', 'popup_text', 'popup_button', 'popup_success', 'popup_discount_code', 'popup_delay',
    ];
    foreach ($keys as $key) {
        admin_setting_save($key, trim((string) post($key, '')));
    }

    // Popup checkboxes
    admin_setting_save('popup_enabled', post('popup_enabled') ? '1' : '0');
    admin_setting_save('popup_collect_email', post('popup_collect_email') ? '1' : '0');

    // Shipping carrier toggles
    admin_setting_save('ship_dhl_enabled', post('ship_dhl_enabled') ? '1' : '0');
    admin_setting_save('ship_fedex_enabled', post('ship_fedex_enabled') ? '1' : '0');

    if (!empty($_FILES['logo_file'])) {
        $logo = admin_settings_upload($_FILES['logo_file']);
        if ($logo) {
            admin_setting_save('logo_path', $logo);
        }
    }

    if (!empty($_FILES['popup_image_file'])) {
        $popImg = admin_settings_upload($_FILES['popup_image_file']);
        if ($popImg) {
            admin_setting_save('popup_image', $popImg);
        }
    }

    $rates = post('rate', []);
    if (is_array($rates)) {
        $rUpd = db()->prepare('UPDATE currency_rates SET rate_from_gbp = ? WHERE code = ?');
        foreach ($rates as $code => $rate) {
            $rUpd->execute([(float) $rate, strtoupper((string) $code)]);
        }
    }
    flash('success', 'Settings saved.');
    header('Location: settings.php');
    exit;
}

$rates = currency_rates();
$logoPath = (string) setting('logo_path', 'assets/images/logo.png');

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-8">
  <h1 class="font-display text-4xl">Settings</h1>
  <p class="text-sm text-stone-500 mt-1">Store content, branding, contact details and currency.</p>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-6 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm flex items-center gap-2"><?= admin_icon('check-circle', 'w-4 h-4') ?><?= e($msg) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-6 items-start pb-24">
  <?= csrf_field() ?>

  <!-- Branding -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('image', 'w-4 h-4 text-stone-400') ?> Branding</h2>
    <div class="flex items-center gap-4">
      <div class="w-24 h-24 rounded-xl bg-stone-900 flex items-center justify-center p-2 shrink-0">
        <?php if ($logoPath && file_exists(ROOT_PATH . '/' . $logoPath)): ?>
          <img src="<?= e(asset($logoPath)) ?>" class="max-w-full max-h-full object-contain" alt="Logo">
        <?php else: ?>
          <span class="text-white/40 text-xs">No logo</span>
        <?php endif; ?>
      </div>
      <div class="flex-1">
        <label class="text-xs text-stone-500 mb-1 block">Upload logo (PNG/SVG)</label>
        <input type="file" name="logo_file" accept="image/*" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-stone-900 file:text-white file:px-4 file:py-2 file:text-xs">
        <p class="text-xs text-stone-400 mt-2">Shown in the site header.</p>
      </div>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Store name</label>
      <input name="store_name" value="<?= e((string) setting('store_name', 'By Claudia Darlene')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Meta description (SEO)</label>
      <textarea name="meta_description" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e((string) setting('meta_description', 'Luxury hair for every curl story.')) ?></textarea>
    </div>
  </div>

  <!-- Homepage content -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('layout', 'w-4 h-4 text-stone-400') ?> Homepage content</h2>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Promo banner</label>
      <textarea name="promo_banner" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e((string) setting('promo_banner', '')) ?></textarea>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Hero title</label>
      <input name="hero_title" value="<?= e((string) setting('hero_title', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Hero subtitle</label>
      <textarea name="hero_subtitle" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e((string) setting('hero_subtitle', '')) ?></textarea>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">About blurb</label>
      <textarea name="about_blurb" rows="3" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e((string) setting('about_blurb', '')) ?></textarea>
    </div>
  </div>

  <!-- Contact & social -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('phone', 'w-4 h-4 text-stone-400') ?> Contact &amp; social</h2>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Phone</label>
        <input name="contact_phone" value="<?= e((string) setting('contact_phone', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Email</label>
        <input name="contact_email" value="<?= e((string) setting('contact_email', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Address</label>
      <input name="contact_address" value="<?= e((string) setting('contact_address', '')) ?>" placeholder="Optional" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div class="grid gap-3">
      <div class="flex items-center gap-2">
        <span class="w-8 shrink-0 text-stone-400"><?= admin_icon('instagram', 'w-4 h-4') ?></span>
        <input name="social_instagram" value="<?= e((string) setting('social_instagram', '')) ?>" placeholder="Instagram URL" class="flex-1 rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="flex items-center gap-2">
        <span class="w-8 shrink-0 text-stone-400"><?= admin_icon('music', 'w-4 h-4') ?></span>
        <input name="social_tiktok" value="<?= e((string) setting('social_tiktok', '')) ?>" placeholder="TikTok URL" class="flex-1 rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="flex items-center gap-2">
        <span class="w-8 shrink-0 text-stone-400"><?= admin_icon('facebook', 'w-4 h-4') ?></span>
        <input name="social_facebook" value="<?= e((string) setting('social_facebook', '')) ?>" placeholder="Facebook URL" class="flex-1 rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
    </div>
  </div>

  <!-- Shipping -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="font-medium flex items-center gap-2"><?= admin_icon('truck', 'w-4 h-4 text-stone-400') ?> Shipping</h2>
        <p class="text-xs text-stone-500 mt-1">Default rate and country overrides are managed on the Shipping page.</p>
      </div>
      <a href="shipping.php" class="shrink-0 rounded-full border border-stone-300 px-4 py-2 text-xs hover:bg-stone-50">Manage shipping →</a>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Default shipping rate (GBP)</label>
        <input name="shipping_flat" type="number" step="0.01" value="<?= e((string) setting('shipping_flat', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Free shipping over (GBP)</label>
        <input name="free_shipping_threshold" type="number" step="0.01" value="<?= e((string) setting('free_shipping_threshold', '')) ?>" placeholder="0 = disabled" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
    </div>

    <div class="border-t border-stone-100 pt-4 space-y-4">
      <p class="text-xs text-stone-500">Optional carrier labels for fulfilment. Checkout pricing uses default + country overrides, not these rates.</p>

      <!-- DHL -->
      <div class="rounded-xl border border-stone-200 p-4">
        <label class="flex items-center justify-between mb-3">
          <span class="font-medium text-sm flex items-center gap-2"><span class="inline-block px-2 py-0.5 rounded bg-[#ffcc00] text-[#d40511] text-[11px] font-bold tracking-wide">DHL</span> Enable DHL</span>
          <input type="checkbox" name="ship_dhl_enabled" value="1" <?= setting('ship_dhl_enabled', '0') === '1' ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4">
        </label>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Label shown to customer</label>
            <input name="ship_dhl_label" value="<?= e((string) setting('ship_dhl_label', 'DHL Express')) ?>" placeholder="DHL Express" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </div>
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Rate (GBP)</label>
            <input name="ship_dhl_rate" type="number" step="0.01" value="<?= e((string) setting('ship_dhl_rate', '')) ?>" placeholder="0.00" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </div>
        </div>
      </div>

      <!-- FedEx -->
      <div class="rounded-xl border border-stone-200 p-4">
        <label class="flex items-center justify-between mb-3">
          <span class="font-medium text-sm flex items-center gap-2"><span class="inline-block px-2 py-0.5 rounded bg-[#4d148c] text-white text-[11px] font-bold tracking-wide">FedEx</span> Enable FedEx</span>
          <input type="checkbox" name="ship_fedex_enabled" value="1" <?= setting('ship_fedex_enabled', '0') === '1' ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4">
        </label>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Label shown to customer</label>
            <input name="ship_fedex_label" value="<?= e((string) setting('ship_fedex_label', 'FedEx International')) ?>" placeholder="FedEx International" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </div>
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Rate (GBP)</label>
            <input name="ship_fedex_rate" type="number" step="0.01" value="<?= e((string) setting('ship_fedex_rate', '')) ?>" placeholder="0.00" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Marketing popup -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4 lg:col-span-2">
    <div class="flex items-center justify-between">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('megaphone', 'w-4 h-4 text-stone-400') ?> Marketing popup</h2>
      <label class="flex items-center gap-2 text-sm">
        <span class="text-stone-500">Enabled</span>
        <input type="checkbox" name="popup_enabled" value="1" <?= setting('popup_enabled', '0') === '1' ? 'checked' : '' ?> class="accent-emerald-500 w-5 h-5">
      </label>
    </div>
    <p class="text-xs text-stone-400 -mt-2">Shows a phone-number signup popup to visitors. Subscribers appear under <a href="subscribers.php" class="underline">Subscribers</a>.</p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2">
        <label class="text-xs text-stone-500 mb-1 block">Heading</label>
        <input name="popup_title" value="<?= e((string) setting('popup_title', 'Get 10% Off Your First Order')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="sm:col-span-2">
        <label class="text-xs text-stone-500 mb-1 block">Body text</label>
        <textarea name="popup_text" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e((string) setting('popup_text', 'Join our VIP list by text and be first to know about new drops, restocks and exclusive offers.')) ?></textarea>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Button label</label>
        <input name="popup_button" value="<?= e((string) setting('popup_button', 'Sign me up')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Discount code to reveal (optional)</label>
        <input name="popup_discount_code" value="<?= e((string) setting('popup_discount_code', '')) ?>" placeholder="e.g. WELCOME10" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="sm:col-span-2">
        <label class="text-xs text-stone-500 mb-1 block">Success message</label>
        <input name="popup_success" value="<?= e((string) setting('popup_success', "You're on the list! Check your phone for your welcome offer.")) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Show after (seconds)</label>
        <input name="popup_delay" type="number" min="0" step="1" value="<?= e((string) setting('popup_delay', '4')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm mb-1">
          <input type="checkbox" name="popup_collect_email" value="1" <?= setting('popup_collect_email', '0') === '1' ? 'checked' : '' ?> class="accent-emerald-500 w-5 h-5">
          <span class="text-stone-600">Also collect email</span>
        </label>
      </div>
      <div class="sm:col-span-2 flex items-center gap-4">
        <?php $popImg = (string) setting('popup_image', ''); ?>
        <div class="w-20 h-20 rounded-xl bg-stone-100 flex items-center justify-center overflow-hidden shrink-0">
          <?php if ($popImg && file_exists(ROOT_PATH . '/' . $popImg)): ?>
            <img src="<?= e(asset($popImg)) ?>" class="w-full h-full object-cover" alt="Popup image">
          <?php else: ?><span class="text-stone-300 text-xs">No image</span><?php endif; ?>
        </div>
        <div class="flex-1">
          <label class="text-xs text-stone-500 mb-1 block">Popup image (optional)</label>
          <input type="file" name="popup_image_file" accept="image/*" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-stone-900 file:text-white file:px-4 file:py-2 file:text-xs">
        </div>
      </div>
    </div>
  </div>

  <!-- Currency -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4 lg:col-span-2">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('pound-sterling', 'w-4 h-4 text-stone-400') ?> Currency rates <span class="text-xs text-stone-400 font-normal">per 1 GBP</span></h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ($rates as $code => $r): ?>
        <div>
          <label class="text-xs text-stone-500 mb-1 block"><?= e($code) ?> <span class="text-stone-400"><?= e($r['symbol'] ?? '') ?></span></label>
          <input type="number" step="0.000001" name="rate[<?= e($code) ?>]" value="<?= e((string) $r['rate_from_gbp']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]" <?= $code === 'GBP' ? 'readonly' : '' ?>>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="lg:col-span-2 fixed bottom-0 left-0 right-0 lg:left-64 bg-white/90 backdrop-blur border-t border-stone-200 px-6 py-3 flex justify-end z-30">
    <button class="rounded-full bg-stone-900 text-white px-8 py-3 text-sm font-medium hover:bg-stone-800 transition flex items-center gap-2"><?= admin_icon('save', 'w-4 h-4') ?> Save settings</button>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
