<?php
declare(strict_types=1);

$pageTitle = 'Hair by Claudia Darlene – Luxury Hair for Every Curl Story';
$pageDescription = 'Premium wigs, bundles, and crochet for natural hair textures.';
$canonical = url();

// Real hair product photos only (skip seed SVG placeholders)
$products = db()->query(
    "SELECT * FROM products
     WHERE is_active = 1
       AND image IS NOT NULL
       AND image != ''
       AND image NOT LIKE '%.svg'
       AND image NOT LIKE '%logo.png'
     ORDER BY is_featured DESC, id DESC
     LIMIT 12"
)->fetchAll();

$testimonials = db()->query(
    'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC'
)->fetchAll();

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order ASC LIMIT 4')->fetchAll();

// Signature style covers — real product lifestyle shots
$signatureStyles = [
    [
        'title' => 'Wigs & Units',
        'subtitle' => 'HD lace · Ready to wear',
        'href' => url('index.php?page=shop&category=wigs'),
        'image' => 'assets/images/products/wp/ohemaahair.jpg',
    ],
    [
        'title' => 'Wefted Bundles',
        'subtitle' => 'True-to-texture volume',
        'href' => url('index.php?page=shop&category=bundles'),
        'image' => 'assets/images/products/wp/Afro-kinky-curly-bundles.jpg',
    ],
    [
        'title' => 'Crochet Collection',
        'subtitle' => 'Feather-light installs',
        'href' => url('index.php?page=shop&category=crochet'),
        'image' => 'assets/images/products/wp/IMG_5929-scaled.jpg',
    ],
    [
        'title' => 'The Color Edit',
        'subtitle' => 'Custom shades for every curl',
        'href' => url('index.php?page=shop&category=color'),
        'image' => 'assets/images/products/wp/IMG_2742-2.jpg',
    ],
];

$lookbook = [
    'assets/images/products/wp/Claudia.jpg',
    'assets/images/products/wp/Kiki3.jpg',
    'assets/images/products/wp/Facetune_16-07-2025-09-34-33-1-scaled.jpg',
    'assets/images/products/wp/siren-1--scaled.jpg',
    'assets/images/products/wp/Ohemaaheair2.jpg',
    'assets/images/products/wp/it-girl.jpg',
    'assets/images/products/wp/Hair1-scaled.jpg',
    'assets/images/products/wp/Mainphoto-1.jpg',
    'assets/images/products/wp/Facetune_15-12-2025-13-20-46-2.jpg',
    'assets/images/newsletter-model.png',
];
$lookbook = array_values(array_filter($lookbook, fn ($img) => file_exists(ROOT_PATH . '/' . $img)));

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero-vibe relative min-h-[92vh] flex items-end sm:items-center justify-center overflow-hidden">
  <div class="absolute inset-0">
    <img src="<?= e(asset('assets/images/hero-bg.png')) ?>" alt="Textured hair by Claudia Darlene" class="hero-bg absolute inset-0 w-full h-full object-cover object-top">
    <div class="hero-vibe__wash absolute inset-0" aria-hidden="true"></div>
    <div class="hero-vibe__vignette absolute inset-0" aria-hidden="true"></div>
    <div class="hero-vibe__grain absolute inset-0" aria-hidden="true"></div>
  </div>

  <div class="relative z-10 w-full px-6 pb-16 pt-32 sm:py-28">
    <div class="max-w-3xl mx-auto text-center text-white">
      <p class="hero-copy hero-vibe__eyebrow text-[11px] sm:text-xs tracking-[0.4em] uppercase mb-6">
        Luxury hair for every curl story
      </p>
      <h1 class="hero-copy hero-vibe__title font-display text-6xl sm:text-7xl md:text-8xl lg:text-[7.5rem] font-medium leading-[0.92] mb-6">
        <?= e(setting('hero_title', 'The Color Edit')) ?>
      </h1>
      <p class="hero-copy hero-vibe__sub text-base sm:text-lg md:text-xl text-white/90 max-w-xl mx-auto mb-10 leading-relaxed">
        <?= e(setting('hero_subtitle', 'Reflect your inner beauty through everyday color.')) ?>
      </p>
      <a href="<?= e(url('index.php?page=shop')) ?>" class="hero-cta inline-block btn-blush px-10 py-4 text-sm tracking-[0.16em] uppercase font-medium shadow-soft">
        Shop Now
      </a>
    </div>
  </div>

  <a href="#about-brand" class="hero-vibe__scroll absolute bottom-6 left-1/2 -translate-x-1/2 z-10 hidden sm:flex flex-col items-center gap-2 text-white/70 hover:text-white transition" aria-label="Scroll to about">
    <span class="text-[10px] tracking-[0.28em] uppercase">Scroll</span>
    <span class="hero-vibe__scroll-line block w-px h-8 bg-white/60"></span>
  </a>
</section>

<section id="about-brand" class="py-20 sm:py-24">
  <div class="max-w-3xl mx-auto px-6 text-center reveal">
    <h2 class="font-display text-4xl sm:text-5xl mb-5">About Hair by Claudia Darlene</h2>
    <p class="text-brand-soft leading-relaxed text-base sm:text-lg mb-8">
      <?= e(setting('about_blurb')) ?>
    </p>
    <a href="<?= e(url('index.php?page=about')) ?>" class="inline-block btn-ink px-7 py-3 text-sm tracking-[0.12em] uppercase">Learn More</a>
  </div>
</section>

<section class="pb-16 px-4 sm:px-6 lg:px-8">
  <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-5">
    <a href="<?= e(url('index.php?page=shop&category=bundles')) ?>" class="reveal group relative min-h-[380px] rounded-[28px] overflow-hidden bg-brand-ink">
      <video class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105" autoplay muted loop playsinline preload="metadata" poster="<?= e(asset('assets/images/newsletter-model.png')) ?>">
        <source src="<?= e(asset('assets/videos/hero-bundles.mp4')) ?>" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/85 via-brand-ink/25 to-transparent"></div>
      <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
        <h3 class="font-display text-3xl sm:text-4xl mb-2 group-hover:translate-x-1 transition drop-shadow-sm">Effortless Beauty, Naturally You.</h3>
        <p class="text-sm text-white/80 max-w-md">Textures that blend seamlessly, feel luxurious, and move with you.</p>
      </div>
    </a>
    <a href="<?= e(url('index.php?page=shop&category=wigs')) ?>" class="reveal group relative min-h-[380px] rounded-[28px] overflow-hidden bg-brand-ink">
      <?php if (file_exists(ROOT_PATH . '/assets/images/about/founder.jpg')): ?>
        <img src="<?= e(asset('assets/images/about/founder.jpg')) ?>" alt="Rooted in culture" class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105">
      <?php elseif (file_exists(ROOT_PATH . '/assets/videos/founder.mov')): ?>
        <video class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105" autoplay muted loop playsinline preload="metadata">
          <source src="<?= e(asset('assets/videos/founder.mov')) ?>" type="video/quicktime">
          <source src="<?= e(asset('assets/videos/founder.mov')) ?>" type="video/mp4">
        </video>
      <?php else: ?>
        <img src="<?= e(asset('assets/images/products/wp/Claudia.jpg')) ?>" alt="Rooted in culture" class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105">
      <?php endif; ?>
      <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/85 via-brand-ink/30 to-transparent"></div>
      <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
        <h3 class="font-display text-3xl sm:text-4xl mb-2 group-hover:translate-x-1 transition">Rooted in Culture. Raised in Confidence.</h3>
        <p class="text-sm text-white/80 max-w-md">A movement to uplift textured queens, one curl at a time.</p>
      </div>
    </a>
  </div>
</section>

<section class="py-8 sm:py-12 pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-10 reveal">
      <h2 class="font-display text-4xl sm:text-5xl mb-3">Signature Styles</h2>
      <p class="text-brand-soft">Wigs, bundles, crochet, and color — made for natural textures.</p>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
      <?php foreach ($signatureStyles as $style): ?>
        <a href="<?= e($style['href']) ?>" class="reveal signature-card group relative aspect-[3/4] rounded-[24px] overflow-hidden bg-brand-mist">
          <?php if (file_exists(ROOT_PATH . '/' . $style['image'])): ?>
            <img src="<?= e(asset($style['image'])) ?>" alt="<?= e($style['title']) ?>" class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-110">
          <?php endif; ?>
          <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent"></div>
          <div class="absolute inset-x-0 bottom-0 p-5 text-white">
            <h3 class="font-display text-2xl leading-tight mb-1"><?= e($style['title']) ?></h3>
            <p class="text-xs text-white/75 tracking-wide"><?= e($style['subtitle']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="py-8 sm:py-12 pb-24 hair-collection">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12 reveal">
      <h2 class="font-display text-4xl sm:text-5xl mb-3">Our Collection</h2>
      <p class="text-brand-soft">Discover our best-selling wigs and bundles — crafted for texture, volume, and effortless beauty.</p>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
      <?php foreach ($products as $product): ?>
        <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-12">
      <a href="<?= e(url('index.php?page=shop')) ?>" class="inline-block btn-ink px-8 py-3 text-sm tracking-[0.12em] uppercase">Shop All Hair</a>
    </div>
  </div>
</section>

<?php if ($lookbook): ?>
<section class="pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8 text-center reveal">
    <h2 class="font-display text-4xl sm:text-5xl mb-3">The Lookbook</h2>
    <p class="text-brand-soft">Real texture. Real volume. Real confidence.</p>
  </div>
  <div class="lookbook-rail flex gap-3 sm:gap-4 overflow-x-auto no-scrollbar px-4 sm:px-6 lg:px-8 pb-2">
    <?php foreach ($lookbook as $i => $img): ?>
      <a href="<?= e(url('index.php?page=shop')) ?>" class="lookbook-shot shrink-0 relative rounded-2xl overflow-hidden bg-brand-mist <?= $i % 3 === 1 ? 'w-44 sm:w-56 aspect-[3/4]' : 'w-40 sm:w-52 aspect-[4/5]' ?>">
        <img src="<?= e(asset($img)) ?>" alt="Claudia Darlene hair look" class="w-full h-full object-cover" loading="lazy">
        <div class="absolute inset-0 bg-brand-ink/0 hover:bg-brand-ink/15 transition"></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($ig = setting('social_instagram', '')): ?>
    <div class="text-center mt-8">
      <a href="<?= e($ig) ?>" target="_blank" rel="noopener" class="inline-block rounded-full bg-[#E1306C] text-white px-7 py-3 text-sm tracking-wide hover:opacity-90 transition">View more on Instagram</a>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="drop-section relative py-24 sm:py-28 overflow-hidden">
  <div class="drop-section__bg absolute inset-0" aria-hidden="true"></div>
  <div class="drop-section__orb drop-section__orb--left absolute -left-24 top-10 w-72 h-72 rounded-full blur-3xl opacity-50" aria-hidden="true"></div>
  <div class="drop-section__orb drop-section__orb--right absolute -right-20 bottom-0 w-80 h-80 rounded-full blur-3xl opacity-40" aria-hidden="true"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-[1fr_1.15fr_1fr] gap-6 lg:gap-10 items-center">
    <div class="hidden lg:block drop-shot drop-shot--left h-[32rem] rounded-[28px] overflow-hidden -rotate-2">
      <img src="<?= e(asset('assets/images/newsletter-model.png')) ?>" alt="Claudia Darlene model" class="w-full h-full object-cover">
    </div>

    <div class="text-center reveal py-4 lg:py-8">
      <p class="text-xs tracking-[0.32em] uppercase text-brand-ink/55 mb-4">Stay ready for exclusive offers</p>
      <h2 class="font-display text-5xl sm:text-6xl lg:text-7xl leading-[0.95] mb-5">Don't Miss<br>the Next Drop!</h2>
      <p class="text-brand-ink/75 text-base sm:text-lg mb-9 max-w-md mx-auto leading-relaxed">
        Exclusive sales, limited-time offers, and surprise discounts on your favourite textures.
      </p>
      <form id="home-newsletter" class="drop-form max-w-md mx-auto p-3 sm:p-4 rounded-[28px] bg-white/95 backdrop-blur shadow-soft space-y-2.5 text-left" method="post" action="<?= e(url('api/subscribe.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="source" value="newsletter">
        <input type="tel" name="phone" required placeholder="Phone number" autocomplete="tel" class="w-full rounded-full bg-brand-cream/80 px-5 py-3.5 text-sm placeholder:text-brand-ink/35 border border-brand-ink/5 focus:outline-none focus:ring-2 focus:ring-brand-ink/15">
        <input type="email" name="email" required placeholder="Email address" autocomplete="email" class="w-full rounded-full bg-brand-cream/80 px-5 py-3.5 text-sm placeholder:text-brand-ink/35 border border-brand-ink/5 focus:outline-none focus:ring-2 focus:ring-brand-ink/15">
        <button class="btn-ink w-full px-8 py-3.5 text-sm tracking-[0.14em] uppercase">Subscribe</button>
      </form>
      <p class="mt-4 text-[11px] tracking-wide text-brand-ink/45">Text + email updates · Unsubscribe anytime</p>
    </div>

    <div class="hidden lg:block drop-shot drop-shot--right h-[32rem] rounded-[28px] overflow-hidden rotate-2">
      <img src="<?= e(asset('assets/images/newsletter-model-2.png')) ?>" alt="Claudia Darlene model" class="w-full h-full object-cover">
    </div>
  </div>
</section>

<section class="py-20 sm:py-24">
  <div class="max-w-3xl mx-auto px-6 text-center reveal">
    <p class="text-xs tracking-[0.28em] uppercase text-brand-soft mb-3">What Our Customers Are Saying</p>
    <h2 class="font-display text-4xl sm:text-5xl mb-10">Testimonials</h2>
    <?php foreach ($testimonials as $i => $t): ?>
      <div data-testimonial class="<?= $i === 0 ? '' : 'hidden' ?>">
        <blockquote class="font-display text-2xl sm:text-3xl italic leading-snug text-brand-ink/90 mb-6">
          “<?= e($t['quote']) ?>”
        </blockquote>
        <p class="tracking-[0.2em] uppercase text-xs text-brand-soft"><?= e($t['author_name']) ?></p>
      </div>
    <?php endforeach; ?>
    <div class="flex justify-center gap-2 mt-8">
      <?php foreach ($testimonials as $i => $t): ?>
        <button type="button" data-testimonial-dot class="w-2.5 h-2.5 rounded-full <?= $i === 0 ? 'bg-brand-ink' : 'bg-brand-ink/20' ?>" aria-label="Testimonial <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
