<?php
declare(strict_types=1);
$storeName = setting('store_name', 'By Claudia Darlene');
$logoPath = (string) setting('logo_path', 'assets/images/logo.png');
$phone = setting('contact_phone', '+44 7342 590296');
$email = setting('contact_email', 'info@byclaudiadarlene.com');
$ig = setting('social_instagram', '');
$tiktok = setting('social_tiktok', '');
$fb = setting('social_facebook', '');
$footerVisual = file_exists(ROOT_PATH . '/assets/images/newsletter-model.png')
    ? 'assets/images/newsletter-model.png'
    : (file_exists(ROOT_PATH . '/assets/images/about/founder.jpg') ? 'assets/images/about/founder.jpg' : '');
?>
  </main>

  <footer class="site-footer text-brand-cream relative overflow-hidden">
    <div class="site-footer__glow" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Trust line -->
      <div class="site-footer__trust flex flex-wrap items-center justify-center gap-x-6 gap-y-2 py-5 text-[11px] tracking-[0.22em] uppercase text-white/40 border-b border-white/10">
        <span>Worldwide Shipping</span>
        <span class="hidden sm:inline text-brand-blush/50">◆</span>
        <span>DHL Express</span>
        <span class="hidden sm:inline text-brand-blush/50">◆</span>
        <span>Secure Checkout</span>
        <span class="hidden sm:inline text-brand-blush/50">◆</span>
        <span>Made to Order</span>
      </div>

      <div class="py-14 lg:py-16 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8">
        <!-- Brand -->
        <div class="lg:col-span-4">
          <a href="<?= e(url('index.php?page=home')) ?>" class="inline-block mb-5">
            <img src="<?= e(asset($logoPath)) ?>" alt="<?= e($storeName) ?>" class="h-14 w-auto object-contain brightness-0 invert">
          </a>
          <p class="font-display text-3xl sm:text-4xl leading-[1.05] text-white mb-4 max-w-sm">
            Luxury hair for every curl story.
          </p>
          <p class="text-sm text-white/45 leading-relaxed max-w-sm mb-7">
            Ethically sourced textures designed to enhance, never overpower — crafted for queens who wear their natural beauty with pride.
          </p>
          <div class="space-y-2 text-sm">
            <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $phone)) ?>" class="flex items-center gap-3 text-brand-blush hover:text-white transition">
              <span class="w-px h-4 bg-brand-blush/60"></span>
              <?= e($phone) ?>
            </a>
            <a href="mailto:<?= e($email) ?>" class="flex items-center gap-3 text-white/60 hover:text-white transition">
              <span class="w-px h-4 bg-white/25"></span>
              <?= e($email) ?>
            </a>
          </div>
          <?php if ($ig || $tiktok || $fb): ?>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-7 text-xs tracking-[0.16em] uppercase text-white/40">
              <?php if ($ig): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener" class="hover:text-brand-blush transition">Instagram</a><?php endif; ?>
              <?php if ($tiktok): ?><a href="<?= e($tiktok) ?>" target="_blank" rel="noopener" class="hover:text-brand-blush transition">TikTok</a><?php endif; ?>
              <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener" class="hover:text-brand-blush transition">Facebook</a><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Links -->
        <div class="lg:col-span-2">
          <h4 class="text-[11px] tracking-[0.24em] uppercase text-brand-blush mb-5">Shop</h4>
          <ul class="space-y-3 text-sm text-white/55">
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop')) ?>">All Hair</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=wigs')) ?>">Wigs &amp; Units</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=bundles')) ?>">Bundles</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=crochet')) ?>">Crochet</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shop&category=color')) ?>">Color Edit</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=gift-cards')) ?>">Gift Cards</a></li>
          </ul>
        </div>

        <div class="lg:col-span-2">
          <h4 class="text-[11px] tracking-[0.24em] uppercase text-brand-blush mb-5">Client Care</h4>
          <ul class="space-y-3 text-sm text-white/55">
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=shipping-policy')) ?>">Shipping</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=returns-policy')) ?>">Returns</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=faq')) ?>">FAQ</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=contact')) ?>">Contact</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=account')) ?>">Track Order</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=privacy-policy')) ?>">Privacy</a></li>
            <li><a class="hover:text-white transition" href="<?= e(url('index.php?page=terms')) ?>">Terms</a></li>
          </ul>
        </div>

        <!-- Newsletter panel -->
        <div class="lg:col-span-4">
          <div class="site-footer__panel relative overflow-hidden p-6 sm:p-7 h-full">
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
                <input type="tel" name="phone" required placeholder="Phone number" autocomplete="tel" class="footer-input w-full px-4 py-3 text-sm">
                <input type="email" name="email" required placeholder="Email address" autocomplete="email" class="footer-input w-full px-4 py-3 text-sm">
                <button type="submit" class="w-full py-3.5 text-sm tracking-[0.16em] uppercase bg-brand-blush text-brand-ink hover:bg-brand-blushDeep transition">
                  Subscribe
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-white/10 py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-white/35">
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
