<?php
declare(strict_types=1);

require_login();
$user = current_user();
$pageTitle = 'Wishlist – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action');
    $productId = (int) post('product_id');
    if ($action === 'add' && $productId > 0) {
        try {
            db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
        } catch (Throwable $e) {
            // MySQL uses INSERT IGNORE differently
            try {
                db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
            } catch (Throwable $e2) {
            }
        }
    } elseif ($action === 'remove' && $productId > 0) {
        db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$user['id'], $productId]);
    }
    redirect('index.php?page=wishlist');
}

$stmt = db()->prepare(
    'SELECT p.* FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.id DESC'
);
$stmt->execute([$user['id']]);
$products = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="font-display text-5xl text-center mb-10">Wishlist</h1>
    <?php if (!$products): ?>
      <p class="text-center text-brand-soft">Your wishlist is empty.</p>
    <?php else: ?>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
        <?php foreach ($products as $product): ?>
          <div>
            <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
            <form method="post" class="mt-2 text-center">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <button class="text-xs underline text-brand-soft">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
