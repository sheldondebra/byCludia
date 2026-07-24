<?php
declare(strict_types=1);

$pageTitle = 'Page Not Found – Hair by Claudia Darlene';
$pageDescription = "Sorry, we couldn't find the page you were looking for.";
$robots = 'noindex, follow';
$canonical = url('404');

if (!headers_sent()) {
    http_response_code(404);
}

$suggestions = [];
try {
    $suggestions = db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 4')->fetchAll();
} catch (Throwable $e) {
    $suggestions = [];
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-20 sm:py-28">
  <div class="max-w-xl mx-auto px-6 text-center">
    <p class="text-[11px] tracking-[0.28em] uppercase text-brand-soft mb-5">Error 404</p>
    <h1 class="font-display text-4xl sm:text-5xl leading-tight mb-4">Page not found</h1>
    <p class="text-brand-soft leading-relaxed mb-10">
      This link may be outdated, mistyped, or the page has moved. Head home or browse the collection.
    </p>
    <div class="flex flex-wrap justify-center gap-3">
      <a href="<?= e(url()) ?>" class="btn-ink px-8 py-3.5 text-sm tracking-[0.14em] uppercase">Back to home</a>
      <a href="<?= e(url('shop')) ?>" class="rounded-full border border-brand-ink/15 px-8 py-3.5 text-sm tracking-[0.14em] uppercase hover:bg-brand-ink hover:text-white transition">Shop collection</a>
    </div>
    <p class="mt-8 text-sm text-brand-soft">
      Need help? <a href="<?= e(url('contact')) ?>" class="underline underline-offset-4 decoration-brand-ink/20 hover:decoration-brand-ink hover:text-brand-ink transition">Contact us</a>
    </p>
  </div>

  <?php if ($suggestions): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-20">
      <h2 class="font-display text-2xl sm:text-3xl text-center mb-8">You might love these</h2>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
        <?php foreach ($suggestions as $product): ?>
          <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
