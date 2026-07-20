<?php
declare(strict_types=1);

$pageTitle = 'Compare – Hair by Claudia Darlene';
$pageDescription = 'Compare hair textures, prices, and details side by side.';
$robots = 'noindex, follow';

$ids = [];
foreach (explode(',', (string) get('ids', '')) as $raw) {
    $id = (int) trim($raw);
    if ($id > 0) {
        $ids[$id] = $id;
    }
}
$ids = array_slice(array_values($ids), 0, 4);

$products = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.id IN (' . $placeholders . ') AND p.is_active = 1'
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $products[] = $byId[$id];
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20" data-compare-page>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="font-display text-5xl text-center mb-4">Compare</h1>
    <p class="text-center text-brand-soft mb-10 max-w-xl mx-auto">Line up your favourites side by side. Add items using the compare icon on any product.</p>

    <div data-compare-empty class="<?= $products ? 'hidden' : '' ?> text-center bg-white/70 rounded-3xl border border-brand-ink/5 p-10">
      <p class="text-brand-soft mb-4">You haven&rsquo;t added anything to compare yet.</p>
      <a href="<?= e(url('index.php?page=shop')) ?>" class="inline-block rounded-full bg-brand-ink text-white px-6 py-3 text-sm tracking-[0.14em] uppercase">Browse shop</a>
    </div>

    <?php if ($products): ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] border-separate border-spacing-x-4">
          <tbody>
            <tr>
              <th class="w-32 align-bottom text-left text-xs tracking-[0.16em] uppercase text-brand-soft"></th>
              <?php foreach ($products as $p): ?>
                <td class="align-top w-64">
                  <div class="bg-white/70 border border-brand-ink/5 rounded-3xl overflow-hidden">
                    <a href="<?= e(url('index.php?page=product&slug=' . urlencode((string) $p['slug']))) ?>">
                      <?php if (!empty($p['image']) && file_exists(ROOT_PATH . '/' . $p['image'])): ?>
                        <div class="aspect-[4/5] overflow-hidden"><img src="<?= e(asset((string) $p['image'])) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover"></div>
                      <?php else: ?>
                        <div class="aspect-[4/5] bg-gradient-to-br from-brand-mist via-brand-blush/50 to-[#e8c4a8] flex items-end p-4"><span class="font-display text-lg text-brand-ink/70 leading-tight"><?= e(explode('–', $p['name'])[0]) ?></span></div>
                      <?php endif; ?>
                    </a>
                    <div class="p-3 text-center">
                      <button type="button" data-compare-remove="<?= (int) $p['id'] ?>" class="text-xs underline text-brand-soft hover:text-brand-ink">Remove</button>
                    </div>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Name</th>
              <?php foreach ($products as $p): ?><td class="py-3 font-display text-lg leading-snug"><?= e($p['name']) ?></td><?php endforeach; ?>
            </tr>
            <tr class="border-t">
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Price</th>
              <?php foreach ($products as $p): ?>
                <td class="py-3 font-medium">
                  <?= money((float) $p['base_price']) ?>
                  <?= !empty($p['compare_at_price']) ? '<span class="text-brand-soft line-through ml-1 text-sm">' . money((float) $p['compare_at_price']) . '</span>' : '' ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Category</th>
              <?php foreach ($products as $p): ?><td class="py-3 text-sm"><?= e($p['category_name'] ?? '—') ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3">Rating</th>
              <?php foreach ($products as $p): ?><td class="py-3"><?= stars((float) $p['rating']) ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th class="text-left text-xs tracking-[0.16em] uppercase text-brand-soft py-3 align-top">Details</th>
              <?php foreach ($products as $p): ?><td class="py-3 text-sm text-brand-soft"><?= e($p['short_description'] ?? '') ?></td><?php endforeach; ?>
            </tr>
            <tr>
              <th></th>
              <?php foreach ($products as $p): ?>
                <td class="py-3"><a href="<?= e(url('index.php?page=product&slug=' . urlencode((string) $p['slug']))) ?>" class="inline-block rounded-full bg-brand-ink text-white px-5 py-2.5 text-xs tracking-[0.14em] uppercase">View</a></td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
