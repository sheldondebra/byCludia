<?php
declare(strict_types=1);

if (setting('popup_enabled', '0') !== '1') {
    return;
}

$popTitle = (string) setting('popup_title', 'Get 10% Off Your First Order');
$popText = (string) setting('popup_text', 'Join our VIP list by text and be first to know about new drops, restocks and exclusive offers.');
$popButton = (string) setting('popup_button', 'Sign me up');
$popDelay = (int) (setting('popup_delay', '4') ?: 4);
$popImage = (string) setting('popup_image', '');
$hasImage = $popImage !== '' && file_exists(ROOT_PATH . '/' . $popImage);
?>
<div id="mkt-popup" class="fixed inset-0 z-[100] hidden items-center justify-center px-4" aria-modal="true" role="dialog">
  <div class="absolute inset-0 bg-brand-ink/60 backdrop-blur-sm" data-popup-close></div>
  <div class="relative w-full max-w-lg bg-brand-cream rounded-[24px] shadow-soft overflow-hidden grid <?= $hasImage ? 'sm:grid-cols-2' : '' ?> animate-[popIn_.4s_ease]">
    <?php if ($hasImage): ?>
      <div class="hidden sm:block">
        <img src="<?= e(asset($popImage)) ?>" alt="" class="w-full h-full object-cover">
      </div>
    <?php endif; ?>
    <div class="p-7 sm:p-8">
      <button type="button" data-popup-close aria-label="Close" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/70 hover:bg-white flex items-center justify-center text-brand-ink/70 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M6 6l12 12M18 6L6 18"/></svg>
      </button>

      <div data-popup-form-wrap>
        <p class="text-[11px] tracking-[0.18em] uppercase text-brand-blushDeep mb-2">Exclusive offer</p>
        <h2 class="font-display text-3xl leading-tight mb-3"><?= e($popTitle) ?></h2>
        <p class="text-sm text-brand-soft leading-relaxed mb-5"><?= e($popText) ?></p>

        <form id="mkt-popup-form" class="space-y-3">
          <input type="hidden" name="source" value="popup">
          <input type="text" name="name" placeholder="Your name (optional)" class="w-full rounded-xl border border-brand-ink/10 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
          <input type="tel" name="phone" required placeholder="Phone number" autocomplete="tel" class="w-full rounded-xl border border-brand-ink/10 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
          <input type="email" name="email" required placeholder="Email address" autocomplete="email" class="w-full rounded-xl border border-brand-ink/10 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
          <p data-popup-error class="hidden text-xs text-rose-600"></p>
          <button type="submit" class="btn-ink w-full py-3.5 text-sm tracking-[0.14em] uppercase"><?= e($popButton) ?></button>
        </form>
        <p class="text-[11px] text-brand-soft/80 mt-3">By subscribing you agree to receive marketing messages by text and email. Unsubscribe anytime.</p>
      </div>

      <div data-popup-success class="hidden text-center py-6">
        <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="font-display text-2xl mb-2">You're in!</h3>
        <p class="text-sm text-brand-soft" data-popup-success-msg></p>
        <div data-popup-code-wrap class="hidden mt-4">
          <span class="inline-block rounded-full border-2 border-dashed border-brand-blushDeep px-6 py-2.5 font-mono text-lg tracking-widest text-brand-ink" data-popup-code></span>
        </div>
        <button type="button" data-popup-close class="btn-ink mt-6 px-8 py-3 text-sm tracking-[0.14em] uppercase">Start shopping</button>
      </div>
    </div>
  </div>
</div>

<style>@keyframes popIn{from{opacity:0;transform:translateY(16px) scale(.98)}to{opacity:1;transform:none}}</style>
<script>
(function () {
  var popup = document.getElementById('mkt-popup');
  if (!popup) return;
  var KEY = 'bcd_popup_done';
  var delay = <?= max(0, $popDelay) ?> * 1000;

  function open() { popup.classList.remove('hidden'); popup.classList.add('flex'); }
  function close() { popup.classList.add('hidden'); popup.classList.remove('flex'); }
  function markDone() { try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {} }

  var done = false;
  try { done = !!localStorage.getItem(KEY); } catch (e) {}
  if (!done) { window.setTimeout(open, delay); }

  popup.querySelectorAll('[data-popup-close]').forEach(function (el) {
    el.addEventListener('click', function () { markDone(); close(); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { markDone(); close(); } });

  var form = document.getElementById('mkt-popup-form');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var err = popup.querySelector('[data-popup-error]');
    err.classList.add('hidden');
    var btn = form.querySelector('button[type=submit]');
    var original = btn.textContent;
    btn.disabled = true; btn.textContent = 'Please wait…';

    var data = new FormData(form);
    data.append('csrf_token', (window.APP && window.APP.csrf) || '');

    fetch((window.APP ? window.APP.baseUrl : '') + '/api/subscribe.php', { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) {
          markDone();
          popup.querySelector('[data-popup-form-wrap]').classList.add('hidden');
          var ok = popup.querySelector('[data-popup-success]');
          ok.classList.remove('hidden');
          popup.querySelector('[data-popup-success-msg]').textContent = res.message || "You're subscribed!";
          if (res.discount) {
            popup.querySelector('[data-popup-code-wrap]').classList.remove('hidden');
            popup.querySelector('[data-popup-code]').textContent = res.discount;
          }
          if (window.toast) window.toast.success(res.message || "You're on the list", { title: 'Subscribed' });
        } else {
          err.textContent = (res && res.error) || 'Something went wrong. Please try again.';
          err.classList.remove('hidden');
          btn.disabled = false; btn.textContent = original;
          if (window.toast) window.toast.error((res && (res.error || res.message)) || 'Something went wrong');
        }
      })
      .catch(function () {
        err.textContent = 'Network error. Please try again.';
        err.classList.remove('hidden');
        btn.disabled = false; btn.textContent = original;
        if (window.toast) window.toast.error('Network error. Please try again.');
      });
  });
})();
</script>
