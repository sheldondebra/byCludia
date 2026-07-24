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
            try {
                db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
            } catch (Throwable $e2) {
            }
        }
    } elseif ($action === 'remove' && $productId > 0) {
        db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$user['id'], $productId]);
    } elseif ($action === 'rotate_share') {
        user_wishlist_share_token((int) $user['id'], true);
        flash('success', 'New share link created. The old link no longer works.');
    }
    redirect('index.php?page=wishlist');
}

$stmt = db()->prepare(
    'SELECT p.* FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.id DESC'
);
$stmt->execute([$user['id']]);
$products = $stmt->fetchAll();

$shareToken = user_wishlist_share_token((int) $user['id']);
$shareUrl = wishlist_share_url($shareToken);
$firstName = trim(explode(' ', (string) $user['name'])[0] ?: 'My');
$shareTitle = $firstName . "'s wishlist – " . (setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene');

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="font-display text-5xl text-center mb-4">Wishlist</h1>
    <p class="text-center text-brand-soft mb-10 max-w-xl mx-auto">Save pieces you love, then share your list with friends and family.</p>

    <div class="max-w-2xl mx-auto mb-12 rounded-3xl border border-brand-ink/10 bg-white/80 p-5 sm:p-6 shadow-soft/40">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
        <div>
          <p class="text-xs tracking-[0.18em] uppercase text-brand-soft mb-1">Share wishlist</p>
          <p class="text-sm text-brand-ink/80">Anyone with this link can view your saved pieces (read-only).</p>
        </div>
        <form method="post" onsubmit="return confirm('Create a new link? The current share link will stop working.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rotate_share">
          <button type="submit" class="text-xs underline text-brand-soft hover:text-brand-ink whitespace-nowrap">New link</button>
        </form>
      </div>

      <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <input type="text" readonly value="<?= e($shareUrl) ?>"
          class="flex-1 rounded-full border border-brand-ink/15 bg-brand-mist/40 px-4 py-2.5 text-sm text-brand-ink/80 truncate"
          aria-label="Wishlist share link"
          onclick="this.select()">
        <button type="button" data-copy-link="<?= e($shareUrl) ?>"
          class="btn-ink inline-flex items-center justify-center gap-2 px-5 py-2.5 text-xs tracking-[0.12em] uppercase shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H16a4 4 0 010 8h-2.5M10.5 6H8a4 4 0 000 8h2.5M8 10h8"/></svg>
          Copy link
        </button>
      </div>

      <?php
      $shareImage = '';
      $shareLabel = 'Share via';
      require ROOT_PATH . '/includes/partials/share.php';
      ?>
    </div>

    <?php if (!$products): ?>
      <p class="text-center text-brand-soft">Your wishlist is empty.</p>
      <p class="text-center mt-4">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="btn-ink inline-flex px-8 py-3 text-sm tracking-[0.12em] uppercase">Browse the shop</a>
      </p>
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
