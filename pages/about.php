<?php
declare(strict_types=1);

$pageTitle = 'About – Hair by Claudia Darlene';
$pageDescription = 'Hair by Claudia Darlene provides premium, ethically sourced hair extensions and wigs for a flawless, natural look — luxury hair that enhances your beauty effortlessly.';
$canonical = url('about');
$ogImage = 'assets/images/about/hero.jpg';
require ROOT_PATH . '/includes/header.php';
?>

<!-- Hero -->
<section class="py-12 sm:py-16 lg:py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
    <div class="reveal order-2 lg:order-1">
      <p class="font-display italic text-3xl sm:text-4xl text-brand-blushDeep mb-4">Claudia Darlene</p>
      <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl leading-[1.05] mb-6">Hair by Claudia Darlene — Luxury Hair, Timeless Beauty</h1>
      <p class="text-brand-soft leading-relaxed text-lg mb-8 max-w-xl">
        At Hair by Claudia Darlene, we provide premium hair extensions and wigs for a flawless, natural look. Elevate your style with luxury hair that enhances your beauty effortlessly.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-block px-8 py-3.5 text-sm tracking-[0.14em] uppercase">Shop the Collection</a>
        <a href="<?= e(url('index.php?page=contact')) ?>" class="rounded-full border border-brand-ink/15 px-8 py-3.5 text-sm tracking-[0.14em] uppercase hover:bg-brand-ink hover:text-white transition">Contact Us</a>
      </div>
    </div>
    <div class="reveal order-1 lg:order-2 relative">
      <div class="absolute -inset-3 rounded-[32px] bg-brand-blush/40 -rotate-2"></div>
      <div class="relative aspect-[4/5] rounded-[28px] overflow-hidden shadow-soft">
        <img src="<?= e(asset('assets/images/about/hero.jpg')) ?>" alt="Hair by Claudia Darlene" class="w-full h-full object-cover object-top">
      </div>
    </div>
  </div>
</section>

<!-- Ethos statement -->
<section class="py-8">
  <div class="max-w-4xl mx-auto px-6 text-center reveal">
    <p class="font-display text-2xl sm:text-3xl lg:text-4xl leading-snug text-brand-ink">
      We ensure our hair products are <span class="text-brand-blushDeep">ethically sourced</span>. We create high-quality hair that sets trends. We provide premium hair for the beauty industry.
    </p>
  </div>
</section>

<!-- Mission & Vision -->
<section class="py-12 sm:py-16">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-2 gap-6">
    <div class="reveal rounded-[28px] bg-brand-mist/70 border border-brand-ink/5 p-8 sm:p-10">
      <span class="inline-flex w-12 h-12 rounded-full bg-white items-center justify-center text-brand-ink shadow-soft mb-5">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-7-10a7 7 0 0114 0c0 5.65-7 10-7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
      </span>
      <h2 class="font-display text-3xl mb-3">Our Mission</h2>
      <p class="text-brand-soft leading-relaxed">To empower women with premium, ethically sourced hair that celebrates natural beauty and textured roots — blending luxury with authenticity. We exist to create hair that feels like you — bold, beautiful, and unapologetically real.</p>
    </div>
    <div class="reveal rounded-[28px] bg-brand-ink text-brand-cream p-8 sm:p-10">
      <span class="inline-flex w-12 h-12 rounded-full bg-brand-blush items-center justify-center text-brand-ink mb-5">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
      </span>
      <h2 class="font-display text-3xl mb-3">Our Vision</h2>
      <p class="text-brand-cream/75 leading-relaxed">To lead a global movement where every woman feels seen, confident, and celebrated through inclusive, sustainable, and high-quality hair solutions. We envision a world where textured beauty is not just accepted, but elevated.</p>
    </div>
  </div>
</section>

<!-- About Founder -->
<section class="py-12 sm:py-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
    <div class="reveal relative">
      <div class="relative aspect-[4/5] rounded-[28px] overflow-hidden shadow-soft bg-brand-mist">
        <video class="w-full h-full object-cover" autoplay muted loop playsinline preload="metadata" poster="<?= e(asset('assets/images/about/founder.jpg')) ?>">
          <source src="<?= e(asset('assets/videos/founder.mov')) ?>" type="video/mp4">
          <img src="<?= e(asset('assets/images/about/founder.jpg')) ?>" alt="Claudia Darlene, Founder" class="w-full h-full object-cover">
        </video>
      </div>
    </div>
    <div class="reveal">
      <p class="text-[11px] tracking-[0.22em] uppercase text-brand-blushDeep mb-3">Our Story</p>
      <h2 class="font-display text-4xl sm:text-5xl mb-6">About the Founder</h2>
      <div class="space-y-4 text-brand-soft leading-relaxed">
        <p>She's walked global runways, lived in cities across three continents, and styled her way through boardrooms and beauty campaigns — but behind the gloss, there was always a struggle: <em>Where is the hair that truly matches us?</em></p>
        <p>For years, she searched. From salons in Istanbul to markets in Accra, Claudia spent her life trying to find curls that looked like hers — thick, coiled, textured, and alive. And time after time, she was met with disappointment. Nothing ever quite fit. The market wasn't made for us — at least, not for all of us.</p>
        <p>That's where Hair by Claudia Darlene was born. This brand was built from frustration, yes — but also from love. It's a love letter to the girls who've been told their hair is "too big," "too distracting," or "too much." To the women who've been told to straighten it, tone it down, or make it more "presentable." No more.</p>
        <p>Our hair is not unprofessional. It is not a problem to fix. It is power. It is presence. It is who we are. And now, we get to wear it — boldly, beautifully, and without apology. Welcome to Hair by Claudia Darlene. We're not asking for space anymore — we're taking it back, one curl at a time.</p>
      </div>
    </div>
  </div>
</section>

<!-- Quote -->
<section class="pb-16 sm:pb-24">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="reveal grid lg:grid-cols-[1.4fr_1fr] gap-8 lg:gap-12 items-center bg-brand-mist/60 rounded-[32px] overflow-hidden border border-brand-ink/5">
      <div class="p-8 sm:p-12 lg:p-16 order-2 lg:order-1">
        <svg class="w-10 h-10 text-brand-blushDeep mb-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7.17 6A5.17 5.17 0 002 11.17V18h6.83v-6.83H5.5A1.67 1.67 0 017.17 9.5V6zm9 0A5.17 5.17 0 0011 11.17V18h6.83v-6.83H14.5A1.67 1.67 0 0116.17 9.5V6z"/></svg>
        <p class="font-display text-3xl sm:text-4xl lg:text-5xl leading-tight mb-6">Confidence starts at the roots. As a Hair CEO, I don't just sell beauty — I empower crowns.</p>
        <p class="font-medium text-brand-ink">Claudia Darlene</p>
        <p class="text-sm text-brand-soft">CEO, Hair by Claudia Darlene</p>
      </div>
      <div class="order-1 lg:order-2 h-72 lg:h-full min-h-[320px]">
        <img src="<?= e(asset('assets/images/about/founder.jpg')) ?>" alt="Claudia Darlene" class="w-full h-full object-cover">
      </div>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
