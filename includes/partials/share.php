<?php
declare(strict_types=1);

/**
 * Reusable social share bar.
 * Set before including:
 *   $shareUrl   (string) absolute URL to share
 *   $shareTitle (string) title/text
 *   $shareImage (string) optional absolute image URL (for Pinterest)
 *   $shareLabel (string) optional label before buttons (default "Share")
 *   $shareNative (bool) show "More" native share button (default true)
 */
$shareUrl = $shareUrl ?? current_url();
$shareTitle = $shareTitle ?? setting('store_name', 'By Claudia Darlene');
$shareImage = $shareImage ?? '';
$shareLabel = $shareLabel ?? 'Share';
$shareNative = $shareNative ?? true;

$u = rawurlencode($shareUrl);
$t = rawurlencode($shareTitle);
$img = $shareImage !== '' ? rawurlencode($shareImage) : '';

$links = [
    'whatsapp' => [
        'label' => 'WhatsApp',
        'href' => 'https://wa.me/?text=' . rawurlencode($shareTitle . ' ' . $shareUrl),
        'svg' => '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/>',
    ],
    'facebook' => [
        'label' => 'Facebook',
        'href' => 'https://www.facebook.com/sharer/sharer.php?u=' . $u,
        'svg' => '<path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.9h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/>',
    ],
    'x' => [
        'label' => 'X',
        'href' => 'https://twitter.com/intent/tweet?text=' . $t . '&url=' . $u,
        'svg' => '<path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.22-6.82-5.97 6.82H1.66l7.73-8.83L1.25 2.25h6.83l4.71 6.23 5.45-6.23zm-1.16 17.52h1.83L7.01 4.13H5.05l12.03 15.64z"/>',
    ],
    'pinterest' => [
        'label' => 'Pinterest',
        'href' => 'https://pinterest.com/pin/create/button/?url=' . $u . ($img ? '&media=' . $img : '') . '&description=' . $t,
        'svg' => '<path d="M12 2C6.48 2 2 6.48 2 12c0 4.24 2.64 7.86 6.36 9.31-.09-.79-.17-2 .03-2.86.18-.78 1.17-4.97 1.17-4.97s-.3-.6-.3-1.48c0-1.39.81-2.43 1.81-2.43.85 0 1.27.64 1.27 1.41 0 .86-.55 2.14-.83 3.33-.24.99.5 1.8 1.48 1.8 1.77 0 3.13-1.87 3.13-4.57 0-2.39-1.72-4.06-4.17-4.06-2.84 0-4.51 2.13-4.51 4.33 0 .86.33 1.78.74 2.28.08.1.09.19.07.29-.08.31-.24.99-.28 1.13-.04.18-.15.22-.34.13-1.25-.58-2.03-2.4-2.03-3.86 0-3.14 2.28-6.02 6.58-6.02 3.46 0 6.14 2.46 6.14 5.75 0 3.43-2.16 6.19-5.17 6.19-1.01 0-1.96-.53-2.28-1.15l-.62 2.37c-.22.87-.83 1.96-1.24 2.62.94.29 1.92.44 2.95.44 5.52 0 10-4.48 10-10S17.52 2 12 2z"/>',
    ],
    'email' => [
        'label' => 'Email',
        'href' => 'mailto:?subject=' . $t . '&body=' . rawurlencode($shareTitle . "\n\n" . $shareUrl),
        'svg' => '<path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm0 2v.01L12 13l8-6.99V6H4zm16 2.24l-7.38 6.45a1 1 0 01-1.24 0L4 8.24V18h16V8.24z"/>',
    ],
];

$btnClass = 'w-9 h-9 rounded-full bg-white border border-brand-ink/10 text-brand-ink/70 hover:bg-brand-ink hover:text-white hover:border-brand-ink transition flex items-center justify-center';
?>
<div class="flex flex-wrap items-center gap-2.5" data-share>
  <?php if ($shareLabel !== ''): ?>
    <span class="text-xs tracking-[0.18em] uppercase text-brand-soft mr-1"><?= e($shareLabel) ?></span>
  <?php endif; ?>
  <?php foreach ($links as $key => $l): ?>
    <a href="<?= e($l['href']) ?>" target="_blank" rel="noopener nofollow"
       aria-label="Share on <?= e($l['label']) ?>" title="Share on <?= e($l['label']) ?>"
       class="<?= e($btnClass) ?>">
      <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24"><?= $l['svg'] ?></svg>
    </a>
  <?php endforeach; ?>
  <?php if ($shareNative): ?>
    <button type="button"
      data-native-share
      data-share-url="<?= e($shareUrl) ?>"
      data-share-title="<?= e($shareTitle) ?>"
      aria-label="More ways to share" title="More"
      class="<?= e($btnClass) ?> hidden"
      data-native-share-btn>
      <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
    </button>
  <?php endif; ?>
  <button type="button" data-copy-link="<?= e($shareUrl) ?>"
    aria-label="Copy link" title="Copy link"
    class="<?= e($btnClass) ?>">
    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H16a4 4 0 010 8h-2.5M10.5 6H8a4 4 0 000 8h2.5M8 10h8"/></svg>
  </button>
  <span data-copy-feedback class="text-xs text-emerald-600 hidden">Link copied!</span>
</div>

<?php if (empty($GLOBALS['__share_js_loaded'])): $GLOBALS['__share_js_loaded'] = true; ?>
<script>
document.addEventListener('click', function (e) {
  var nativeBtn = e.target.closest('[data-native-share]');
  if (nativeBtn && navigator.share) {
    e.preventDefault();
    navigator.share({
      title: nativeBtn.getAttribute('data-share-title') || document.title,
      text: nativeBtn.getAttribute('data-share-title') || '',
      url: nativeBtn.getAttribute('data-share-url') || window.location.href
    }).catch(function () {});
    return;
  }

  var btn = e.target.closest('[data-copy-link]');
  if (!btn) return;
  var url = btn.getAttribute('data-copy-link');
  var done = function () {
    var fb = btn.parentElement.querySelector('[data-copy-feedback]');
    if (fb) { fb.classList.remove('hidden'); setTimeout(function () { fb.classList.add('hidden'); }, 2000); }
    if (window.toast) window.toast.success('Link copied', { title: 'Share' });
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copy this link:', url); });
  } else {
    window.prompt('Copy this link:', url);
  }
});
document.querySelectorAll('[data-native-share-btn]').forEach(function (btn) {
  if (navigator.share) {
    btn.classList.remove('hidden');
  }
});
</script>
<?php endif; ?>
