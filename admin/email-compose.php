<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$template = (string) get('template', 'promo');
if (!isset(email_template_types()[$template])) {
    $template = 'promo';
}
$starter = email_template_starter($template);
$coupons = db()->query('SELECT id, code, type, value FROM coupons WHERE is_active = 1 ORDER BY code')->fetchAll();

$prefillSources = array_filter(explode(',', (string) get('sources', 'subscribers,users')));
$prefillStatuses = array_filter(explode(',', (string) get('order_status', '')));
$prefillCustom = trim((string) get('custom', ''));

$error = '';
$previewCount = null;

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = (string) post('action');
    $template = (string) post('template_type', 'promo');
    if (!isset(email_template_types()[$template])) {
        $template = 'promo';
    }

    $subject = trim((string) post('subject'));
    $previewText = trim((string) post('preview_text'));
    $headline = trim((string) post('headline'));
    $eyebrow = trim((string) post('eyebrow'));
    $bodyHtml = (string) post('body_html');
    $ctaLabel = trim((string) post('cta_label'));
    $ctaUrl = trim((string) post('cta_url'));
    $couponCode = trim((string) post('coupon_code'));
    $sources = (array) ($_POST['sources'] ?? []);
    $orderStatuses = (array) ($_POST['order_statuses'] ?? []);
    $customEmails = trim((string) post('custom_emails'));
    $heroExisting = trim((string) post('hero_image_existing'));

    $hero = $heroExisting;
    if (!empty($_FILES['hero_image']) && ($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploaded = email_upload_asset($_FILES['hero_image']);
        if ($uploaded) {
            $hero = $uploaded;
        }
    }

    // Inline image upload (AJAX-style form field)
    if ($action === 'upload_image' && !empty($_FILES['inline_image'])) {
        $path = email_upload_asset($_FILES['inline_image']);
        header('Content-Type: application/json');
        if ($path) {
            echo json_encode(['ok' => true, 'url' => asset($path), 'path' => $path]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Upload failed']);
        }
        exit;
    }

    if ($action === 'live_preview') {
        $html = email_render_html([
            'template_type' => $template,
            'headline' => $headline,
            'eyebrow' => $eyebrow,
            'body' => email_merge_tags($bodyHtml !== '' ? $bodyHtml : '<p></p>', [
                'name' => 'Alex',
                'email' => 'alex@example.com',
                'order_number' => 'CD-1001',
            ], ['coupon_code' => $couponCode]),
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'hero_image' => $hero,
            'coupon_code' => $couponCode,
            'preview_text' => $previewText,
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    $audienceMeta = [
        'sources' => $sources,
        'order_statuses' => $orderStatuses,
        'custom' => $customEmails !== '',
    ];

    if ($subject === '' || trim(strip_tags($bodyHtml)) === '') {
        $error = 'Subject and body are required.';
    } elseif (!mailer_enabled() && in_array($action, ['send', 'test'], true)) {
        $error = 'Enable email sending in Integrations first.';
    } else {
        $user = current_user();
        $payload = [
            'subject' => $subject,
            'preview_text' => $previewText,
            'template_type' => $template,
            'headline' => $headline,
            'eyebrow' => $eyebrow,
            'body_html' => $bodyHtml,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'hero_image' => $hero,
            'coupon_code' => $couponCode,
            'audience' => $audienceMeta,
            'created_by' => $user['id'] ?? null,
        ];

        if ($action === 'preview_count') {
            $recipients = email_collect_audience($sources, $orderStatuses, $customEmails);
            $previewCount = count($recipients);
            // Fall through to re-render form with count
            $starter = [
                'subject' => $subject,
                'preview' => $previewText,
                'eyebrow' => $eyebrow,
                'headline' => $headline,
                'body' => $bodyHtml,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
            ];
            $prefillSources = $sources;
            $prefillStatuses = $orderStatuses;
            $prefillCustom = $customEmails;
        } elseif ($action === 'test') {
            $to = trim((string) (setting('contact_email') ?: ($user['email'] ?? '')));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $error = 'Set a contact email in Settings or use an admin account with email for test sends.';
            } else {
                $result = email_campaign_send($payload, [[
                    'email' => $to,
                    'name' => $user['name'] ?? 'Admin',
                    'source' => 'test',
                    'order_number' => 'TEST-001',
                ]], true);
                flash('success', 'Test email sent to ' . $to . ' (' . $result['sent'] . ' sent, ' . $result['failed'] . ' failed).');
                header('Location: email-campaign.php?id=' . $result['campaign_id']);
                exit;
            }
        } elseif ($action === 'send') {
            $recipients = email_collect_audience($sources, $orderStatuses, $customEmails);
            if (!$recipients) {
                $error = 'No recipients matched your audience. Check sources, filters, or paste emails.';
            } else {
                $result = email_campaign_send($payload, $recipients, false);
                flash('success', 'Campaign sent: ' . $result['sent'] . ' delivered, ' . $result['failed'] . ' failed.');
                header('Location: email-campaign.php?id=' . $result['campaign_id']);
                exit;
            }
        }
    }

    if ($error !== '') {
        $starter = [
            'subject' => $subject ?? $starter['subject'],
            'preview' => $previewText ?? $starter['preview'],
            'eyebrow' => $eyebrow ?? ($starter['eyebrow'] ?? ''),
            'headline' => $headline ?? $starter['headline'],
            'body' => $bodyHtml ?? $starter['body'],
            'cta_label' => $ctaLabel ?? $starter['cta_label'],
            'cta_url' => $ctaUrl ?? $starter['cta_url'],
        ];
        $prefillSources = $sources ?? $prefillSources;
        $prefillStatuses = $orderStatuses ?? $prefillStatuses;
        $prefillCustom = $customEmails ?? $prefillCustom;
    }
}

$livePreview = email_render_html([
    'template_type' => $template,
    'eyebrow' => $starter['eyebrow'] ?? '',
    'headline' => $starter['headline'],
    'body' => email_merge_tags($starter['body'], ['name' => 'Alex', 'email' => 'alex@example.com', 'order_number' => 'CD-1001'], ['coupon_code' => 'SAMPLE10']),
    'cta_label' => $starter['cta_label'],
    'cta_url' => $starter['cta_url'],
    'coupon_code' => $template === 'coupon' ? 'SAMPLE10' : '',
    'preview_text' => $starter['preview'],
]);

require __DIR__ . '/_layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="font-display text-4xl">Compose campaign</h1>
    <p class="text-sm text-stone-500 mt-1">Start from a template, edit freely, add images, then send.</p>
  </div>
  <a href="email.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Back</a>
</div>

<?php if ($error): ?><div class="mb-4 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($error) ?></div><?php endif; ?>
<?php if ($previewCount !== null): ?><div class="mb-4 bg-stone-100 text-stone-700 rounded-xl px-4 py-3 text-sm">Audience preview: <strong><?= (int) $previewCount ?></strong> recipient<?= $previewCount === 1 ? '' : 's' ?> (unsubscribed excluded).</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" id="email-compose-form" class="grid lg:grid-cols-2 gap-6 items-start">
  <?= csrf_field() ?>
  <input type="hidden" name="hero_image_existing" id="hero_image_existing" value="">

  <div class="space-y-5">
    <div class="bg-white rounded-2xl border border-stone-200 p-5 space-y-4">
      <h2 class="font-medium">Template</h2>
      <div class="flex flex-wrap gap-2">
        <?php foreach (email_template_types() as $key => $label): ?>
          <label class="cursor-pointer">
            <input type="radio" name="template_type" value="<?= e($key) ?>" class="peer sr-only" <?= $template === $key ? 'checked' : '' ?> onchange="window.location='email-compose.php?template='+this.value">
            <span class="inline-block rounded-full px-3.5 py-1.5 text-sm border peer-checked:bg-stone-900 peer-checked:text-white peer-checked:border-stone-900 border-stone-200 hover:bg-stone-50"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5 space-y-4">
      <h2 class="font-medium">Content</h2>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Subject</label>
        <input name="subject" required value="<?= e($starter['subject']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Preview text</label>
        <input name="preview_text" value="<?= e($starter['preview']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Eyebrow label</label>
        <input name="eyebrow" value="<?= e($starter['eyebrow'] ?? '') ?>" placeholder="Studio note" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Headline</label>
        <input name="headline" value="<?= e($starter['headline']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Body</label>
        <textarea id="body_html" name="body_html" rows="10" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($starter['body']) ?></textarea>
        <p class="text-xs text-stone-400 mt-1">Merge tags: {{name}} {{email}} {{store_name}} {{coupon_code}} {{order_number}}</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-stone-500 mb-1 block">CTA label</label>
          <input name="cta_label" value="<?= e($starter['cta_label']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="text-xs text-stone-500 mb-1 block">CTA URL</label>
          <input name="cta_url" value="<?= e($starter['cta_url']) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Coupon code (optional)</label>
        <input type="hidden" name="coupon_code" id="coupon_code" value="">
        <select id="coupon_code_select" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
          <option value="">— None —</option>
          <?php foreach ($coupons as $cp): ?>
            <option value="<?= e($cp['code']) ?>"><?= e($cp['code']) ?> (<?= $cp['type'] === 'percent' ? (float) $cp['value'] . '%' : '£' . number_format((float) $cp['value'], 2) ?>)</option>
          <?php endforeach; ?>
        </select>
        <input id="coupon_code_custom" placeholder="Or type a custom code" class="mt-2 w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Hero image</label>
        <input type="file" name="hero_image" accept="image/*" class="w-full text-sm">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Insert image into body</label>
        <div class="flex gap-2 items-center">
          <input type="file" id="inline_image" accept="image/*" class="flex-1 text-sm">
          <button type="button" id="insert-image-btn" class="rounded-full border border-stone-300 px-4 py-2 text-sm hover:bg-stone-50">Insert</button>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-5 space-y-4">
      <h2 class="font-medium">Audience</h2>
      <div class="space-y-2 text-sm">
        <?php
        $sourceOpts = ['subscribers' => 'Newsletter subscribers', 'users' => 'Registered customers', 'orders' => 'Order emails'];
        foreach ($sourceOpts as $val => $lbl): ?>
          <label class="flex items-center gap-2">
            <input type="checkbox" name="sources[]" value="<?= e($val) ?>" class="rounded border-stone-300" <?= in_array($val, $prefillSources, true) ? 'checked' : '' ?>>
            <?= e($lbl) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Order status filter (when Order emails is checked)</label>
        <div class="flex flex-wrap gap-2">
          <?php foreach (['pending', 'cancelled', 'paid', 'processing', 'shipped', 'delivered', 'refunded'] as $st): ?>
            <label class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 px-3 py-1 text-xs">
              <input type="checkbox" name="order_statuses[]" value="<?= $st ?>" <?= in_array($st, $prefillStatuses, true) ? 'checked' : '' ?>>
              <?= ucfirst($st) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-stone-400 mt-1">Leave unchecked to include all order emails. For failed-order campaigns, choose Pending and/or Cancelled.</p>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Extra emails (paste)</label>
        <textarea name="custom_emails" rows="3" placeholder="one@example.com, two@example.com" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm"><?= e($prefillCustom) ?></textarea>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button name="action" value="preview_count" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Count recipients</button>
      <button name="action" value="test" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Send test</button>
      <button name="action" value="send" onclick="return confirm('Send this campaign to the selected audience now?')" class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800">Send now</button>
    </div>
  </div>

  <div class="lg:sticky lg:top-6">
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-stone-100 flex items-center justify-between">
        <p class="text-sm font-medium">Live preview</p>
        <span class="text-xs text-stone-400">Sample recipient</span>
      </div>
      <div class="bg-[#FBF7F2] p-4 max-h-[80vh] overflow-auto">
        <iframe id="email-preview-frame" title="Email preview" class="w-full min-h-[640px] bg-transparent border-0" srcdoc="<?= e($livePreview) ?>"></iframe>
      </div>
    </div>
  </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
  const form = document.getElementById('email-compose-form');
  const frame = document.getElementById('email-preview-frame');
  let timer = null;
  let previewing = false;

  function syncCoupon() {
    const custom = document.getElementById('coupon_code_custom')?.value?.trim() || '';
    const selected = document.getElementById('coupon_code_select')?.value || '';
    document.getElementById('coupon_code').value = custom || selected;
  }

  async function refreshPreview() {
    if (previewing) return;
    syncCoupon();
    if (window.tinymce && tinymce.get('body_html')) {
      tinymce.get('body_html').save();
    }
    previewing = true;
    try {
      const fd = new FormData(form);
      fd.set('action', 'live_preview');
      const res = await fetch('email-compose.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const html = await res.text();
      frame.srcdoc = html;
    } catch (e) {
      // keep last preview
    } finally {
      previewing = false;
    }
  }

  function schedulePreview() {
    clearTimeout(timer);
    timer = setTimeout(refreshPreview, 350);
  }

  tinymce.init({
    selector: '#body_html',
    height: 320,
    menubar: false,
    plugins: 'lists link image code',
    toolbar: 'undo redo | styles | bold italic | bullist numlist | link image | code',
    skin_url: 'https://cdn.jsdelivr.net/npm/tinymce@7.6.0/skins/ui/oxide',
    content_css: 'https://cdn.jsdelivr.net/npm/tinymce@7.6.0/skins/content/default/content.min.css',
    branding: false,
    license_key: 'gpl',
    setup: function (editor) {
      editor.on('change keyup SetContent', schedulePreview);
    }
  });

  ['preview_text', 'eyebrow', 'headline', 'cta_label', 'cta_url'].forEach(function (name) {
    form.elements[name]?.addEventListener('input', schedulePreview);
    form.elements[name]?.addEventListener('change', schedulePreview);
  });
  document.getElementById('coupon_code_select')?.addEventListener('change', function () {
    syncCoupon();
    schedulePreview();
  });
  document.getElementById('coupon_code_custom')?.addEventListener('input', function () {
    syncCoupon();
    schedulePreview();
  });
  form.querySelector('input[name="hero_image"]')?.addEventListener('change', function () {
    // Hero uploads on send; for preview, note that live file isn't available until send
    schedulePreview();
  });
  form.addEventListener('submit', syncCoupon);

  document.getElementById('insert-image-btn')?.addEventListener('click', async function () {
    const input = document.getElementById('inline_image');
    if (!input.files || !input.files[0]) { alert('Choose an image first'); return; }
    const fd = new FormData(form);
    fd.set('action', 'upload_image');
    fd.set('inline_image', input.files[0]);
    const res = await fetch('email-compose.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) { alert(data.error || 'Upload failed'); return; }
    if (tinymce.get('body_html')) {
      tinymce.get('body_html').insertContent('<p><img src="' + data.url + '" alt="" style="max-width:100%;height:auto;border-radius:12px;"></p>');
      schedulePreview();
    }
  });
})();
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
