<?php
declare(strict_types=1);
$storeName = setting('store_name', 'By Claudia Darlene');
$logoPath = (string) setting('logo_path', 'assets/images/logo.png');
$phone = setting('contact_phone', '+44 7342 590296');
$email = setting('contact_email', 'info@byclaudiadarlene.com');
$ig = setting('social_instagram', '');
$tiktok = setting('social_tiktok', '');
$fb = setting('social_facebook', '');
$wa = whatsapp_number($phone);
$waHref = $wa !== '' ? 'https://wa.me/' . $wa : '';
$footerVisual = file_exists(ROOT_PATH . '/assets/images/newsletter-model.png')
    ? 'assets/images/newsletter-model.png'
    : (file_exists(ROOT_PATH . '/assets/images/about/founder.jpg') ? 'assets/images/about/founder.jpg' : '');
?>
  </main>

  <footer class="site-footer text-brand-cream relative overflow-hidden">
    <div class="site-footer__glow" aria-hidden="true"></div>

    <div class="site-footer__inner relative w-full max-w-7xl mx-auto">
      <!-- Trust line -->
      <div class="site-footer__trust flex items-center justify-between sm:justify-center gap-2 sm:gap-x-6 py-4 sm:py-5 text-[10px] sm:text-[11px] tracking-[0.14em] sm:tracking-[0.22em] uppercase text-white/40 border-b border-white/10">
        <span class="flex-1 sm:flex-none text-center">Worldwide Shipping</span>
        <span class="hidden sm:inline text-brand-blush/50" aria-hidden="true">◆</span>
        <span class="flex-1 sm:flex-none text-center">Secure Checkout</span>
        <span class="hidden sm:inline text-brand-blush/50" aria-hidden="true">◆</span>
        <span class="flex-1 sm:flex-none text-center">Made to Order</span>
      </div>

      <div class="site-footer__grid py-10 sm:py-12 lg:py-16">
        <!-- Brand -->
        <div class="site-footer__brand">
          <a href="<?= e(url('index.php?page=home')) ?>" class="inline-block mb-5">
            <img src="<?= e(asset($logoPath)) ?>" alt="<?= e($storeName) ?>" class="site-footer__logo w-auto object-contain brightness-0 invert">
          </a>
          <p class="font-display text-[1.75rem] sm:text-3xl md:text-4xl leading-[1.05] text-white mb-4 max-w-none md:max-w-sm">
            Luxury hair for every curl story.
          </p>
          <p class="text-sm text-white/45 leading-relaxed max-w-none md:max-w-sm mb-7">
            Ethically sourced textures designed to enhance, never overpower — crafted for queens who wear their natural beauty with pride.
          </p>
          <div class="space-y-2 text-sm">
            <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $phone)) ?>" class="flex items-center gap-3 text-brand-blush hover:text-white transition">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.28a1 1 0 01.95.68l1.1 3.3a1 1 0 01-.27 1.1l-1.5 1.5a12 12 0 005.66 5.66l1.5-1.5a1 1 0 011.1-.27l3.3 1.1a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C8.82 21 3 15.18 3 8V7a2 2 0 012-2z"/></svg>
              <?= e($phone) ?>
            </a>
            <a href="mailto:<?= e($email) ?>" class="flex items-center gap-3 text-white/60 hover:text-white transition">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 8l-10 6L2 8"/></svg>
              <?= e($email) ?>
            </a>
          </div>
          <?php if ($ig || $tiktok || $fb || $waHref !== ''): ?>
            <div class="flex flex-wrap items-center gap-3 mt-7 text-white/45">
              <?php if ($waHref !== ''): ?>
                <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp"
                  class="w-11 h-11 sm:w-10 sm:h-10 rounded-full border border-white/15 flex items-center justify-center hover:text-[#25D366] hover:border-[#25D366]/50 transition">
                  <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
                </a>
              <?php endif; ?>
              <?php if ($ig): ?>
                <a href="<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram"
                  class="w-11 h-11 sm:w-10 sm:h-10 rounded-full border border-white/15 flex items-center justify-center hover:text-brand-blush hover:border-brand-blush/50 transition">
                  <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
              <?php endif; ?>
              <?php if ($tiktok): ?>
                <a href="<?= e($tiktok) ?>" target="_blank" rel="noopener" aria-label="TikTok" title="TikTok"
                  class="w-11 h-11 sm:w-10 sm:h-10 rounded-full border border-white/15 flex items-center justify-center hover:text-brand-blush hover:border-brand-blush/50 transition">
                  <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.3a6.34 6.34 0 0010.86 4.43V13a8.27 8.27 0 004.84 1.55V11.1a4.83 4.83 0 01-.26-.04z"/></svg>
                </a>
              <?php endif; ?>
              <?php if ($fb): ?>
                <a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook"
                  class="w-11 h-11 sm:w-10 sm:h-10 rounded-full border border-white/15 flex items-center justify-center hover:text-brand-blush hover:border-brand-blush/50 transition">
                  <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.9h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/></svg>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Links -->
        <div class="site-footer__links">
          <div>
            <h4 class="text-[11px] tracking-[0.24em] uppercase text-brand-blush mb-5">Collection</h4>
            <ul class="space-y-3 text-sm text-white/55">
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop')) ?>">Our Collection</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=wigs')) ?>">Wigs &amp; Units</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=bundles')) ?>">Bundles</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=crochet')) ?>">Crochet</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=color')) ?>">Color Edit</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=gift-cards')) ?>">Gift Cards</a></li>
            </ul>
          </div>
          <div>
            <h4 class="text-[11px] tracking-[0.24em] uppercase text-brand-blush mb-5">Client Care</h4>
            <ul class="space-y-3 text-sm text-white/55">
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shipping-policy')) ?>">Shipping</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=returns-policy')) ?>">Returns</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=faq')) ?>">FAQ</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=contact')) ?>">Contact</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=track')) ?>">Track Order</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=privacy-policy')) ?>">Privacy</a></li>
              <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=terms')) ?>">Terms</a></li>
            </ul>
          </div>
        </div>

        <!-- Newsletter panel -->
        <div class="site-footer__newsletter">
          <div class="site-footer__panel relative overflow-hidden p-5 sm:p-7 h-full">
            <?php if ($footerVisual): ?>
              <div class="absolute inset-0 opacity-[0.18]">
                <img src="<?= e(asset($footerVisual)) ?>" alt="" class="w-full h-full object-cover">
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-[#1c1917] via-[#1c1917]/92 to-[#1c1917]/75"></div>
            <?php endif; ?>
            <div class="relative">
              <h4 class="font-display text-2xl sm:text-3xl text-white mb-2">Join the inner circle</h4>
              <p class="text-sm text-white/55 leading-relaxed mb-5">
                Private drops, restocks, and offers — sent by text and email.
              </p>
              <form id="footer-newsletter" class="space-y-2.5" method="post" action="<?= e(url('api/subscribe.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="footer">
                <input type="tel" name="phone" required placeholder="Phone number" autocomplete="tel" class="footer-input w-full px-4 py-3.5 text-sm">
                <input type="email" name="email" required placeholder="Email address" autocomplete="email" class="footer-input w-full px-4 py-3.5 text-sm">
                <button type="submit" class="w-full py-3.5 text-sm tracking-[0.16em] uppercase bg-brand-blush text-brand-ink hover:bg-brand-blushDeep transition">
                  Subscribe
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="site-footer__legal border-t border-white/10 py-5 sm:py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-white/35">
        <p>&copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.</p>
        <p>
          Designed &amp; developed by
          <a href="https://www.tecunitgh.com" target="_blank" rel="noopener" class="text-white/55 hover:text-brand-blush transition">Tecunit</a>
        </p>
      </div>
    </div>
  </footer>

  <?php require ROOT_PATH . '/includes/partials/popup.php'; ?>
  <?php require ROOT_PATH . '/includes/partials/toast.php'; ?>

  <script>
    window.APP = {
      baseUrl: <?= json_encode(rtrim($config['app_url'], '/')) ?>,
      csrf: <?= json_encode(csrf_token()) ?>,
      currency: <?= json_encode(current_currency()) ?>,
      toasts: <?= json_encode(isset($flashToasts) && is_array($flashToasts) ? $flashToasts : [], JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
