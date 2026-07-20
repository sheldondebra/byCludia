<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $id = (int) post('id');
    $action = post('action');
    if ($id) {
        $r = db()->prepare('SELECT product_id FROM reviews WHERE id = ?');
        $r->execute([$id]);
        $productId = (int) $r->fetchColumn();
        if ($action === 'approve') {
            db()->prepare('UPDATE reviews SET is_approved = 1 WHERE id = ?')->execute([$id]);
        } elseif ($action === 'unapprove') {
            db()->prepare('UPDATE reviews SET is_approved = 0 WHERE id = ?')->execute([$id]);
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        }
        if ($productId) {
            recompute_product_rating($productId);
        }
    }
    header('Location: reviews.php' . (get('filter') ? '?filter=' . urlencode((string) get('filter')) : ''));
    exit;
}

$filter = (string) get('filter', 'pending');
$where = match ($filter) {
    'approved' => 'WHERE r.is_approved = 1',
    'all' => '',
    default => 'WHERE r.is_approved = 0',
};
$reviews = db()->query(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug FROM reviews r '
    . 'JOIN products p ON p.id = r.product_id ' . $where . ' ORDER BY r.id DESC LIMIT 300'
)->fetchAll();

$counts = ['pending' => 0, 'approved' => 0, 'all' => 0];
foreach (db()->query('SELECT is_approved, COUNT(*) c FROM reviews GROUP BY is_approved') as $row) {
    if ((int) $row['is_approved'] === 1) $counts['approved'] = (int) $row['c'];
    else $counts['pending'] = (int) $row['c'];
}
$counts['all'] = $counts['pending'] + $counts['approved'];

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-2">Reviews</h1>
<p class="text-sm text-stone-500 mb-6">Moderate customer reviews. Approving updates the product rating automatically.</p>

<div class="flex gap-2 mb-6">
  <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'all' => 'All'] as $val => $lbl): ?>
    <a href="reviews.php?filter=<?= $val ?>" class="rounded-full px-4 py-1.5 text-sm border <?= $filter === $val ? 'bg-stone-900 text-white border-stone-900' : 'border-stone-200 hover:bg-stone-100' ?>"><?= $lbl ?> <span class="opacity-60"><?= $counts[$val] ?></span></a>
  <?php endforeach; ?>
</div>

<div class="space-y-4">
  <?php foreach ($reviews as $r): ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex-1 min-w-[240px]">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-amber-500 text-sm"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
            <?php if ($r['is_approved']): ?>
              <span class="rounded-full px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700">Approved</span>
            <?php else: ?>
              <span class="rounded-full px-2 py-0.5 text-xs bg-amber-100 text-amber-700">Pending</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($r['title'])): ?><p class="font-medium"><?= e($r['title']) ?></p><?php endif; ?>
          <p class="text-sm text-stone-600 mt-1"><?= nl2br(e($r['body'])) ?></p>
          <p class="text-xs text-stone-400 mt-2">
            <?= e($r['author_name']) ?> &middot; <?= e(date('d M Y', strtotime((string) $r['created_at']))) ?> &middot;
            <a href="product-edit.php?id=<?= (int) $r['product_id'] ?>" class="hover:underline"><?= e($r['product_name']) ?></a>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <?php if (!$r['is_approved']): ?>
            <form method="post" action="reviews.php?filter=<?= e($filter) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="rounded-full bg-emerald-600 text-white px-4 py-2 text-xs hover:bg-emerald-700">Approve</button></form>
          <?php else: ?>
            <form method="post" action="reviews.php?filter=<?= e($filter) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="unapprove"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="rounded-full border border-stone-300 px-4 py-2 text-xs hover:bg-stone-100">Unapprove</button></form>
          <?php endif; ?>
          <form method="post" action="reviews.php?filter=<?= e($filter) ?>" onsubmit="return confirm('Delete review?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="rounded-full text-rose-500 hover:text-rose-700 p-2" title="Delete"><?= admin_icon('trash-2') ?></button></form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$reviews): ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-10 text-center text-stone-400">No <?= $filter === 'all' ? '' : $filter ?> reviews.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
