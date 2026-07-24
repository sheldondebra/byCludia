<?php
declare(strict_types=1);

$pageTitle = 'Gift Cards – Hair by Claudia Darlene';
$pageDescription = 'Give the gift of choice with a By Claudia Darlene digital gift card. Delivered instantly by email.';
$canonical = url('gift-cards');

$bounds = gift_amount_bounds();
$denoms = gift_denominations();
$error = null;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session. Please try again.';
    } else {
        $preset = (string) post('amount_preset', '');
        $amount = $preset === 'custom' ? (float) post('amount_custom') : (float) $preset;
        $result = cart_add_gift(
            $amount,
            (string) post('recipient_name', ''),
            (string) post('recipient_email', ''),
            (string) post('sender_name', ''),
            (string) post('message', '')
        );
        if ($result['ok']) {
            flash('success', 'Gift card added to your cart.');
            redirect('index.php?page=cart');
        }
        $error = $result['error'] ?? 'Could not add the gift card.';
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-14 sm:py-20">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <p class="text-xs tracking-[0.28em] uppercase text-brand-soft mb-3">The perfect present</p>
      <h1 class="font-display text-4xl sm:text-5xl mb-4">Gift Cards</h1>
      <p class="text-brand-soft max-w-xl mx-auto">Let them choose their perfect texture. Digital gift cards are delivered by email and can be redeemed on anything in store.</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-6 rounded-2xl bg-rose-50 text-rose-800 px-4 py-3 text-sm max-w-2xl mx-auto"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-start">
      <!-- Preview card -->
      <div class="lg:sticky lg:top-24">
        <div class="relative aspect-[16/10] rounded-[28px] overflow-hidden bg-gradient-to-br from-brand-ink via-[#3a2f28] to-[#6a4a3a] text-brand-cream p-8 flex flex-col justify-between shadow-soft">
          <div class="flex items-center justify-between">
            <span class="font-display text-2xl">Claudia Darlene</span>
            <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8V4H8a2 2 0 00-2 2v2m6 0h6a2 2 0 012 2v0a2 2 0 01-2 2h-6m0-4H6a2 2 0 00-2 2v0a2 2 0 002 2h6m0-4v12"/></svg>
          </div>
          <div>
            <p class="text-xs tracking-[0.2em] uppercase text-brand-cream/60 mb-1">Gift Card</p>
            <p class="font-display text-5xl" data-preview-amount><?= money((float) $denoms[1]) ?></p>
          </div>
          <p class="text-sm text-brand-cream/70" data-preview-to>For someone special</p>
        </div>
        <ul class="mt-6 space-y-2.5 text-sm text-brand-soft">
          <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Delivered instantly by email</li>
          <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Redeemable on any product</li>
          <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Never expires · use the balance any time</li>
        </ul>
      </div>

      <!-- Form -->
      <form method="post" class="space-y-6">
        <?= csrf_field() ?>
        <div>
          <label class="text-sm font-medium block mb-3">Choose an amount</label>
          <div class="grid grid-cols-3 gap-3" data-denoms>
            <?php foreach ($denoms as $i => $d): ?>
              <label class="cursor-pointer">
                <input type="radio" name="amount_preset" value="<?= e((string) $d) ?>" class="peer sr-only gift-amount" data-amount="<?= e((string) $d) ?>" <?= $i === 1 ? 'checked' : '' ?>>
                <span class="block text-center rounded-2xl border border-brand-ink/15 py-3.5 text-sm font-medium peer-checked:border-brand-ink peer-checked:bg-brand-ink peer-checked:text-white transition"><?= money((float) $d) ?></span>
              </label>
            <?php endforeach; ?>
            <label class="cursor-pointer col-span-3">
              <input type="radio" name="amount_preset" value="custom" class="peer sr-only gift-amount" data-amount="custom">
              <span class="block text-center rounded-2xl border border-brand-ink/15 py-3.5 text-sm font-medium peer-checked:border-brand-ink transition">Custom amount</span>
            </label>
          </div>
          <div class="mt-3 hidden" data-custom-wrap>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-brand-soft"><?= e(currency_symbol()) ?></span>
              <input type="number" name="amount_custom" min="<?= (int) $bounds['min'] ?>" max="<?= (int) $bounds['max'] ?>" step="1" placeholder="Enter amount" class="w-full rounded-2xl border border-brand-ink/10 pl-9 pr-4 py-3 text-sm gift-custom-input">
            </div>
            <p class="text-xs text-brand-soft mt-1">Between <?= money($bounds['min']) ?> and <?= money($bounds['max']) ?>.</p>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium block mb-1.5">Recipient name</label>
            <input name="recipient_name" placeholder="Their name" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm" data-to-name>
          </div>
          <div>
            <label class="text-sm font-medium block mb-1.5">Recipient email *</label>
            <input type="email" name="recipient_email" required placeholder="their@email.com" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
        </div>

        <div>
          <label class="text-sm font-medium block mb-1.5">Your name</label>
          <input name="sender_name" placeholder="From" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
        </div>

        <div>
          <label class="text-sm font-medium block mb-1.5">Personal message <span class="text-brand-soft font-normal">(optional)</span></label>
          <textarea name="message" rows="3" maxlength="500" placeholder="Add a note to make it special…" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm"></textarea>
        </div>

        <button class="btn-ink w-full py-4 text-sm tracking-[0.14em] uppercase flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Add gift card to cart
        </button>
      </form>
    </div>
  </div>
</section>

<script>
(() => {
  const radios = document.querySelectorAll('.gift-amount');
  const customWrap = document.querySelector('[data-custom-wrap]');
  const customInput = document.querySelector('.gift-custom-input');
  const previewAmount = document.querySelector('[data-preview-amount]');
  const previewTo = document.querySelector('[data-preview-to]');
  const toName = document.querySelector('[data-to-name]');
  const symbol = <?= json_encode(currency_symbol()) ?>;
  const fmt = (n) => symbol + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });

  const refresh = () => {
    const sel = document.querySelector('.gift-amount:checked');
    const isCustom = sel && sel.dataset.amount === 'custom';
    customWrap.classList.toggle('hidden', !isCustom);
    let amt = isCustom ? parseFloat(customInput.value) : parseFloat(sel ? sel.dataset.amount : 0);
    if (previewAmount) previewAmount.textContent = fmt(amt);
  };
  radios.forEach((r) => r.addEventListener('change', refresh));
  if (customInput) customInput.addEventListener('input', refresh);
  if (toName) toName.addEventListener('input', () => {
    previewTo.textContent = toName.value.trim() ? 'For ' + toName.value.trim() : 'For someone special';
  });
  refresh();
})();
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
