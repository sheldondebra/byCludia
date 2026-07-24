<?php
declare(strict_types=1);

$token = trim((string) ($_GET['token'] ?? ''));
$owner = null;
$products = [];

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $ownerStmt = db()->prepare('SELECT id, name FROM users WHERE wishlist_share_token = ?');
    $ownerStmt->execute([$token]);
    $owner = $ownerStmt->fetch() ?: null;
    if ($owner) {
        $stmt = db()->prepare(
            'SELECT p.* FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND p.is_active = 1 ORDER BY w.id DESC'
        );
        $stmt->execute([(int) $owner['id']]);
        $products = $stmt->fetchAll();
    }
}

$firstName = $owner ? trim(explode(' ', (string) $owner['name'])[0] ?: 'Someone') : '';
$pageTitle = $owner
    ? ($firstName . "'s Wishlist – Hair by Claudia Darlene")
    : 'Wishlist not found – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$canonical = $owner ? wishlist_share_url($token) : url('index.php?page=shop');

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if (!$owner): ?>
      <h1 class="font-display text-5xl text-center mb-4">Wishlist not found</h1>
      <p class="text-center text-brand-soft mb-8">This share link is invalid or has been replaced.</p>
      <p class="text-center">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-flex px-8 py-3 text-sm tracking-[0.12em] uppercase">Shop the collection</a>
      </p>
    <?php else: ?>
      <h1 class="font-display text-5xl text-center mb-3"><?= e($firstName) ?>'s Wishlist</h1>
      <p class="text-center text-brand-soft mb-10">Pieces saved from <?= e(setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene') ?>.</p>

      <?php if (!$products): ?>
        <p class="text-center text-brand-soft">This wishlist is empty for now.</p>
      <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
          <?php foreach ($products as $product): ?>
            <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="text-center mt-12">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-flex px-8 py-3 text-sm tracking-[0.12em] uppercase">Shop the collection</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
