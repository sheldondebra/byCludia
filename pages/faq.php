<?php
declare(strict_types=1);

$pageTitle = 'FAQ – Hair by Claudia Darlene';
$pageDescription = 'Frequently asked questions about shipping, returns, payment options, and caring for your Hair by Claudia Darlene textures.';
$canonical = url('faq');

$faqGroups = [
    'Orders & Shipping' => [
        ['icon' => 'truck', 'q' => 'Do you ship worldwide?', 'a' => 'Yes. Worldwide shipping is available with tracked, insured delivery. Any duties or taxes are determined by your country and may apply on arrival.'],
        ['icon' => 'clock', 'q' => 'How long does shipping take?', 'a' => 'Orders are typically processed within 2–5 business days. Transit times then vary by destination — you\'ll receive tracking as soon as your order ships.'],
        ['icon' => 'package', 'q' => 'Can I track my order?', 'a' => 'Absolutely. Once dispatched we email you a tracking number, and you can also view order status anytime from your account.'],
    ],
    'Payments & Currency' => [
        ['icon' => 'credit-card', 'q' => 'Which payment methods do you accept?', 'a' => 'We accept cards via Stripe, Paystack (card & mobile money), plus Klarna and Clearpay for Buy Now, Pay Later where available.'],
        ['icon' => 'globe', 'q' => 'Can I pay in my own currency?', 'a' => 'Yes. Switch currency in the header between GBP, USD, EUR and GHS. Checkout charges in your selected currency.'],
        ['icon' => 'shield-check', 'q' => 'Is checkout secure?', 'a' => 'Every payment is processed over an encrypted, PCI-compliant connection. We never store your full card details.'],
    ],
    'Products & Care' => [
        ['icon' => 'sparkles', 'q' => 'How do I choose my texture?', 'a' => 'Match our texture names to your natural pattern (e.g. 4B/4C Afro Kinky, 3a–3b Siren Curly). Not sure? Send us a photo and we\'ll help you match.'],
        ['icon' => 'droplet', 'q' => 'How do I care for my hair?', 'a' => 'Wash gently with sulphate-free products, condition regularly, and store on a stand or in a satin bag. Proper care keeps textures soft and long-lasting.'],
        ['icon' => 'palette', 'q' => 'Can I colour or restyle the hair?', 'a' => 'Our hair can be styled and, in most cases, coloured by a professional. You can also add our Professional Hair Color service at checkout.'],
    ],
    'Returns & Support' => [
        ['icon' => 'rotate-ccw', 'q' => 'What is your returns policy?', 'a' => 'Unopened products in original packaging may be eligible for return within 14 days. For hygiene reasons, custom-coloured and used items are final sale.'],
        ['icon' => 'message-circle', 'q' => 'How do I contact support?', 'a' => 'Reach us by email or WhatsApp using the details below — our friendly texture specialists usually reply within one business day.'],
    ],
];

// Flatten for FAQ structured data
$flatFaqs = [];
foreach ($faqGroups as $items) {
    foreach ($items as $it) {
        $flatFaqs[] = $it;
    }
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $flatFaqs),
];

$contactEmail = (string) setting('contact_email', 'info@byclaudiadarlene.com');
$contactPhone = (string) setting('contact_phone', '');
$waNumber = preg_replace('/[^0-9]/', '', $contactPhone);

require ROOT_PATH . '/includes/header.php';
?>

<section class="relative overflow-hidden bg-brand-mist/60 border-b border-brand-ink/5">
  <div class="max-w-3xl mx-auto px-6 py-16 sm:py-20 text-center relative">
    <p class="text-[11px] tracking-[0.22em] uppercase text-brand-blushDeep mb-3">Help Centre</p>
    <h1 class="font-display text-4xl sm:text-6xl mb-4">How can we help?</h1>
    <p class="text-brand-soft mb-8 max-w-xl mx-auto">Answers to the questions we hear most about ordering, shipping, payments and caring for your hair.</p>
    <div class="relative max-w-md mx-auto">
      <span class="absolute left-4 top-1/2 -translate-y-1/2 text-brand-soft">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
      </span>
      <input id="faq-search" type="search" placeholder="Search questions…" autocomplete="off"
        class="w-full rounded-full border border-brand-ink/10 bg-white pl-11 pr-4 py-3.5 text-sm shadow-soft focus:outline-none focus:ring-2 focus:ring-brand-blush">
    </div>
  </div>
</section>

<section class="py-14 sm:py-20">
  <div class="max-w-3xl mx-auto px-6 space-y-12" id="faq-root">
    <?php foreach ($faqGroups as $group => $items): ?>
      <div data-faq-group>
        <h2 class="font-display text-2xl sm:text-3xl mb-5" data-faq-group-title><?= e($group) ?></h2>
        <div class="space-y-3">
          <?php foreach ($items as $faq): ?>
            <details class="group bg-white border border-brand-ink/5 rounded-2xl px-5 py-4 shadow-soft/40 transition hover:border-brand-ink/10" data-faq-item>
              <summary class="cursor-pointer list-none flex items-center gap-4">
                <span class="shrink-0 w-9 h-9 rounded-full bg-brand-mist text-brand-ink flex items-center justify-center">
                  <i data-lucide="<?= e($faq['icon']) ?>" class="w-[18px] h-[18px]"></i>
                </span>
                <span class="font-medium text-brand-ink flex-1" data-faq-q><?= e($faq['q']) ?></span>
                <span class="shrink-0 text-brand-soft transition-transform duration-300 group-open:rotate-45">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                </span>
              </summary>
              <p class="mt-3 pl-[52px] text-brand-soft leading-relaxed" data-faq-a><?= e($faq['a']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p id="faq-empty" class="hidden text-center text-brand-soft py-10">No questions match your search. Try different keywords or contact us below.</p>

    <!-- Contact CTA -->
    <div class="rounded-3xl bg-brand-ink text-brand-cream px-6 py-10 sm:px-10 sm:py-12 text-center">
      <h2 class="font-display text-3xl sm:text-4xl mb-3">Still have questions?</h2>
      <p class="text-brand-cream/70 mb-7 max-w-lg mx-auto">Our texture specialists are happy to help you find the perfect match or track your order.</p>
      <div class="flex flex-wrap justify-center gap-3">
        <a href="mailto:<?= e($contactEmail) ?>" class="rounded-full bg-brand-blush text-brand-ink px-7 py-3.5 text-sm font-semibold tracking-wide hover:bg-brand-blushDeep transition inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4zM4 6l8 6 8-6"/></svg>
          Email us
        </a>
        <?php if ($waNumber !== ''): ?>
          <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener" class="rounded-full border border-brand-cream/25 px-7 py-3.5 text-sm font-medium tracking-wide hover:bg-brand-cream/10 transition inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.4.8-.83 1.02-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
            WhatsApp
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
(() => {
  const search = document.getElementById('faq-search');
  if (!search) return;
  const items = Array.from(document.querySelectorAll('[data-faq-item]'));
  const groups = Array.from(document.querySelectorAll('[data-faq-group]'));
  const empty = document.getElementById('faq-empty');

  search.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    let visible = 0;
    items.forEach((item) => {
      const q = (item.querySelector('[data-faq-q]')?.textContent || '').toLowerCase();
      const a = (item.querySelector('[data-faq-a]')?.textContent || '').toLowerCase();
      const match = term === '' || q.includes(term) || a.includes(term);
      item.classList.toggle('hidden', !match);
      if (match) visible++;
      if (term !== '' && match) item.setAttribute('open', ''); else if (term !== '') item.removeAttribute('open');
    });
    groups.forEach((g) => {
      const anyVisible = g.querySelectorAll('[data-faq-item]:not(.hidden)').length > 0;
      g.classList.toggle('hidden', !anyVisible);
    });
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  });
})();
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
