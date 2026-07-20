<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$testResult = null;

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action', 'save');

    if ($action === 'test_email') {
        $to = trim((string) post('test_email_to'));
        if ($to === '') {
            $testResult = ['ok' => false, 'msg' => 'Enter a recipient email.'];
        } else {
            $ok = send_mail($to, 'Test email from ' . setting('store_name', 'By Claudia Darlene'), '<p>This is a test email. SMTP is working! 🎉</p>');
            $testResult = ['ok' => $ok, 'msg' => $ok ? 'Test email sent to ' . $to : ('Failed: ' . mailer_last_error())];
        }
    } elseif ($action === 'test_sms') {
        $to = trim((string) post('test_sms_to'));
        if ($to === '') {
            $testResult = ['ok' => false, 'msg' => 'Enter a recipient phone number.'];
        } else {
            $r = send_sms($to, 'Test SMS from ' . setting('store_name', 'By Claudia Darlene'));
            $testResult = ['ok' => $r['ok'], 'msg' => $r['ok'] ? 'Test SMS sent to ' . $to : ('Failed: ' . ($r['error'] ?? 'unknown'))];
        }
    } else {
        $checkboxes = [
            'payment_stripe_enabled', 'payment_afterpay_enabled', 'payment_klarna_enabled',
            'payment_paystack_enabled', 'mail_enabled', 'sms_enabled',
        ];
        foreach ($checkboxes as $k) {
            set_setting($k, post($k) ? '1' : '0');
        }
        $texts = [
            'stripe_publishable_key', 'stripe_secret_key',
            'paystack_public_key', 'paystack_secret_key',
            'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass', 'mail_from', 'mail_from_name',
            'splitsms_api_key', 'splitsms_sender', 'splitsms_base_url',
        ];
        foreach ($texts as $k) {
            set_setting($k, trim((string) post($k, '')));
        }
        flash('success', 'Integrations saved.');
        header('Location: integrations.php');
        exit;
    }
}

function field(string $label, string $key, string $type = 'text', string $placeholder = ''): string
{
    $val = (string) setting($key, '');
    return '<div><label class="text-xs text-stone-500 mb-1 block">' . e($label) . '</label>'
        . '<input name="' . e($key) . '" type="' . e($type) . '" value="' . e($val) . '" placeholder="' . e($placeholder) . '" autocomplete="off" '
        . 'class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"></div>';
}

function toggle(string $label, string $key, string $hint = ''): string
{
    $on = setting($key, '0') === '1';
    $hintHtml = $hint ? '<span class="block text-xs text-stone-400">' . e($hint) . '</span>' : '';
    return '<label class="flex items-center justify-between gap-3 text-sm py-1"><span>' . e($label) . $hintHtml . '</span>'
        . '<input type="checkbox" name="' . e($key) . '" value="1" ' . ($on ? 'checked' : '') . ' class="accent-emerald-500 w-5 h-5"></label>';
}

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-8">
  <h1 class="font-display text-4xl">Payments &amp; Integrations</h1>
  <p class="text-sm text-stone-500 mt-1">Connect Stripe, Paystack, email (SMTP) and SMS (SplitSMS).</p>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-6 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm flex items-center gap-2"><?= admin_icon('check-circle', 'w-4 h-4') ?><?= e($msg) ?></div><?php endif; ?>
<?php if ($testResult): ?>
  <div class="mb-6 rounded-xl px-4 py-3 text-sm <?= $testResult['ok'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>"><?= e($testResult['msg']) ?></div>
<?php endif; ?>

<form method="post" class="grid lg:grid-cols-2 gap-6 items-start pb-24">
  <?= csrf_field() ?>

  <!-- Stripe -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('credit-card', 'w-4 h-4 text-stone-400') ?> Stripe</h2>
    <?= toggle('Enable Stripe', 'payment_stripe_enabled') ?>
    <?= field('Publishable key', 'stripe_publishable_key', 'text', 'pk_live_… or pk_test_…') ?>
    <?= field('Secret key', 'stripe_secret_key', 'password', 'sk_live_… or sk_test_…') ?>
    <div class="pt-2 border-t border-stone-100 space-y-1">
      <p class="text-xs text-stone-500 mb-1">Extra Stripe methods (Buy Now, Pay Later):</p>
      <?= toggle('Afterpay / Clearpay', 'payment_afterpay_enabled', 'Requires Stripe + supported currency') ?>
      <?= toggle('Klarna', 'payment_klarna_enabled') ?>
    </div>
  </div>

  <!-- Paystack -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('wallet', 'w-4 h-4 text-stone-400') ?> Paystack</h2>
    <?= toggle('Enable Paystack', 'payment_paystack_enabled', 'Card & Mobile Money (GHS, NGN…)') ?>
    <?= field('Public key', 'paystack_public_key', 'text', 'pk_live_… or pk_test_…') ?>
    <?= field('Secret key', 'paystack_secret_key', 'password', 'sk_live_… or sk_test_…') ?>
    <p class="text-xs text-stone-400">Customers are redirected to Paystack, then returned to your store for verification.</p>
  </div>

  <!-- Email / SMTP -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('mail', 'w-4 h-4 text-stone-400') ?> Email (SMTP)</h2>
    <?= toggle('Enable email sending', 'mail_enabled') ?>
    <div class="grid grid-cols-2 gap-3">
      <?= field('SMTP host', 'smtp_host', 'text', 'smtp.gmail.com') ?>
      <?= field('Port', 'smtp_port', 'text', '587') ?>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Encryption</label>
      <select name="smtp_secure" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
        <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= setting('smtp_secure', 'tls') === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <?= field('SMTP username', 'smtp_user') ?>
      <?= field('SMTP password', 'smtp_pass', 'password') ?>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <?= field('From email', 'mail_from', 'text', 'orders@yourstore.com') ?>
      <?= field('From name', 'mail_from_name', 'text', 'By Claudia Darlene') ?>
    </div>
  </div>

  <!-- SMS / SplitSMS -->
  <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <h2 class="font-medium flex items-center gap-2"><?= admin_icon('message-square', 'w-4 h-4 text-stone-400') ?> SMS (SplitSMS)</h2>
    <?= toggle('Enable SMS notifications', 'sms_enabled') ?>
    <?= field('API key', 'splitsms_api_key', 'password', 'sk_live_… (≈56 chars)') ?>
    <?= field('Sender ID', 'splitsms_sender', 'text', 'ClaudiaD') ?>
    <?= field('Base URL', 'splitsms_base_url', 'text', 'https://www.splitsms.com') ?>
  </div>

  <div class="lg:col-span-2 fixed bottom-0 left-0 right-0 lg:left-60 bg-white/90 backdrop-blur border-t border-stone-200 px-6 py-3 flex justify-end z-30">
    <button class="rounded-full bg-stone-900 text-white px-8 py-3 text-sm font-medium hover:bg-stone-800 transition flex items-center gap-2"><?= admin_icon('save', 'w-4 h-4') ?> Save integrations</button>
  </div>
</form>

<!-- Test tools -->
<div class="grid lg:grid-cols-2 gap-6 mb-10">
  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 flex flex-wrap items-end gap-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_email">
    <div class="flex-1 min-w-[200px]">
      <label class="text-xs text-stone-500 mb-1 block">Send test email to</label>
      <input name="test_email_to" type="email" value="<?= e((string) setting('contact_email', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
    </div>
    <button class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Send test email</button>
  </form>
  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 flex flex-wrap items-end gap-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_sms">
    <div class="flex-1 min-w-[200px]">
      <label class="text-xs text-stone-500 mb-1 block">Send test SMS to</label>
      <input name="test_sms_to" value="<?= e((string) setting('contact_phone', '')) ?>" placeholder="+233…" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
    </div>
    <button class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Send test SMS</button>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
