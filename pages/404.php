<?php
declare(strict_types=1);

$pageTitle = 'Page Not Found – Hair by Claudia Darlene';
$pageDescription = "Sorry, we couldn't find the page you were looking for.";
$robots = 'noindex, follow';

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
  <div class="max-w-2xl mx-auto px-6 text-center">
    <p class="font-display text-[7rem] sm:text-[9rem] leading-none text-brand-blush select-none">404</p>
    <h1 class="font-display text-3xl sm:text-4xl mt-2 mb-4">This page slipped away</h1>
    <p class="text-brand-soft leading-relaxed mb-8">The page you're looking for may have moved, sold out, or never existed. Let's get you back to something beautiful.</p>
    <div class="flex flex-wrap justify-center gap-3">
      <a href="<?= e(url('index.php?page=home')) ?>" class="btn-ink px-8 py-3.5 text-sm tracking-[0.14em] uppercase">Back to home</a>
      <a href="<?= e(url('index.php?page=shop')) ?>" class="rounded-full border border-brand-ink/15 px-8 py-3.5 text-sm tracking-[0.14em] uppercase hover:bg-brand-ink hover:text-white transition">Shop all</a>
    </div>
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
