<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$perPage = 20;

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
    }
    if ($action === 'feature' && $id) {
        db()->prepare('UPDATE products SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
    }
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    }
    $qs = [];
    foreach (['q', 'cat', 'status', 'sort', 'page'] as $k) {
        $val = trim((string) post('return_' . $k));
        if ($val !== '' && !($k === 'status' && $val === 'all') && !($k === 'sort' && $val === 'newest') && !($k === 'cat' && $val === '0') && !($k === 'page' && $val === '1')) {
            $qs[$k] = $val;
        }
    }
    header('Location: products.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

$q = trim((string) get('q', ''));
$catFilter = (int) get('cat', 0);
$statusFilter = (string) get('status', 'all');
$sort = (string) get('sort', 'newest');
$page = max(1, (int) get('page', 1));

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$sales = [];
$salesStmt = db()->query(
    "SELECT oi.product_id AS pid, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS rev "
    . "FROM order_items oi JOIN orders o ON o.id = oi.order_id "
    . "WHERE o.status IN ('paid','processing','shipped','delivered') AND oi.product_id IS NOT NULL "
    . "GROUP BY oi.product_id"
);
foreach ($salesStmt as $r) {
    $sales[(int) $r['pid']] = ['qty' => (int) $r['qty'], 'rev' => (float) $r['rev']];
}

$all = db()->query(
    'SELECT p.*, c.name AS category_name, '
    . '(SELECT COALESCE(SUM(stock),0) FROM product_variants v WHERE v.product_id = p.id) AS stock_total '
    . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id'
)->fetchAll();

foreach ($all as &$p) {
    $pid = (int) $p['id'];
    $p['stock_total'] = (int) $p['stock_total'];
    $p['units_sold'] = $sales[$pid]['qty'] ?? 0;
    $p['revenue'] = $sales[$pid]['rev'] ?? 0.0;
}
unset($p);

$statTotal = count($all);
$statActive = 0;
$statLow = 0;
$statOut = 0;
$totalSold = 0;
foreach ($all as $p) {
    if ($p['is_active']) {
        $statActive++;
    }
    if ($p['stock_total'] === 0) {
        $statOut++;
    } elseif ($p['stock_total'] <= 5) {
        $statLow++;
    }
    $totalSold += $p['units_sold'];
}

$rows = array_filter($all, function ($p) use ($q, $catFilter, $statusFilter) {
    if ($q !== '' && stripos($p['name'], $q) === false && stripos((string) $p['category_name'], $q) === false) {
        return false;
    }
    if ($catFilter && (int) $p['category_id'] !== $catFilter) {
        return false;
    }
    switch ($statusFilter) {
        case 'active':
            return (bool) $p['is_active'];
        case 'hidden':
            return !$p['is_active'];
        case 'low':
            return $p['stock_total'] > 0 && $p['stock_total'] <= 5;
        case 'out':
            return $p['stock_total'] === 0;
        case 'featured':
            return (bool) $p['is_featured'];
        case 'sale':
            return (bool) $p['on_sale'];
    }
    return true;
});
$rows = array_values($rows);

usort($rows, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'best':
            return $b['units_sold'] <=> $a['units_sold'];
        case 'revenue':
            return $b['revenue'] <=> $a['revenue'];
        case 'price_high':
            return (float) $b['base_price'] <=> (float) $a['base_price'];
        case 'price_low':
            return (float) $a['base_price'] <=> (float) $b['base_price'];
        case 'stock_low':
            return $a['stock_total'] <=> $b['stock_total'];
        case 'name':
            return strcasecmp($a['name'], $b['name']);
        default:
            return (int) $b['id'] <=> (int) $a['id'];
    }
});

$totalFiltered = count($rows);
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($rows, $offset, $perPage);
$maxSold = $pageRows ? max(array_map(fn($p) => $p['units_sold'], $pageRows)) : 0;

$filterQs = array_filter([
    'q' => $q,
    'cat' => $catFilter ?: null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'sort' => $sort !== 'newest' ? $sort : null,
], fn($v) => $v !== null && $v !== '');
$hasFilters = $filterQs !== [];

function products_page_url(array $base, int $pageNum): string
{
    $qs = $base;
    if ($pageNum > 1) {
        $qs['page'] = $pageNum;
    }
    return 'products.php' . ($qs ? '?' . http_build_query($qs) : '');
}

function admin_stat_card(string $label, string $value, string $icon, string $tone = 'stone', string $sub = ''): string
{
    $tones = [
        'stone' => 'bg-stone-100 text-stone-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700',
    ];
    $badge = $tones[$tone] ?? $tones['stone'];
    $subHtml = $sub !== '' ? '<p class="text-xs text-stone-400 mt-0.5">' . e($sub) . '</p>' : '';
    return '<div class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5 flex items-center gap-3">'
        . '<span class="w-9 h-9 rounded-xl ' . $badge . ' flex items-center justify-center shrink-0">' . admin_icon($icon, 'w-4 h-4') . '</span>'
        . '<div><p class="text-xs text-stone-500">' . e($label) . '</p><p class="text-lg font-semibold leading-tight">' . $value . '</p>' . $subHtml . '</div>'
        . '</div>';
}

require __DIR__ . '/_layout_top.php';
?>

<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
  <div>
    <h1 class="font-display text-4xl">Products</h1>
    <p class="text-sm text-stone-500 mt-1"><?= number_format($statTotal) ?> products &middot; <?= number_format($totalSold) ?> sold</p>
  </div>
  <a href="product-edit.php" class="flex items-center gap-2 rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800 transition">
    <?= admin_icon('plus') ?> Add product
  </a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <?= admin_stat_card('Total', (string) $statTotal, 'package', 'stone') ?>
  <?= admin_stat_card('Active', (string) $statActive, 'eye', 'emerald', ($statTotal - $statActive) . ' hidden') ?>
  <?= admin_stat_card('Low stock', (string) $statLow, 'alert-triangle', 'amber', '≤ 5 left') ?>
  <?= admin_stat_card('Out of stock', (string) $statOut, 'x-circle', 'rose') ?>
</div>

<div class="flex flex-wrap gap-2 mb-5">
  <?php
  $statusChips = [
      'all' => ['All', $statTotal],
      'active' => ['Active', $statActive],
      'hidden' => ['Hidden', $statTotal - $statActive],
      'featured' => ['Featured', count(array_filter($all, fn($p) => $p['is_featured']))],
      'sale' => ['On sale', count(array_filter($all, fn($p) => $p['on_sale']))],
      'low' => ['Low stock', $statLow],
      'out' => ['Out of stock', $statOut],
  ];
  foreach ($statusChips as $val => [$lbl, $count]):
      $chipQs = array_filter(['q' => $q, 'cat' => $catFilter ?: null, 'status' => $val !== 'all' ? $val : null, 'sort' => $sort !== 'newest' ? $sort : null], fn($v) => $v !== null && $v !== '');
  ?>
    <a href="products.php<?= $chipQs ? '?' . e(http_build_query($chipQs)) : '' ?>"
      class="rounded-full px-3.5 py-1.5 text-sm border <?= $statusFilter === $val ? 'bg-stone-900 text-white border-stone-900' : 'border-stone-200 bg-white hover:bg-stone-100' ?>">
      <?= e($lbl) ?> <span class="opacity-60"><?= (int) $count ?></span>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" class="mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
  <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
  <div class="relative">
    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
    <input name="q" value="<?= e($q) ?>" placeholder="Search products…"
      class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
  </div>
  <select name="cat" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    <option value="0">All categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    <?php foreach (['newest' => 'Newest', 'best' => 'Best selling', 'revenue' => 'Top revenue', 'price_high' => 'Price: high → low', 'price_low' => 'Price: low → high', 'stock_low' => 'Stock: low → high', 'name' => 'Name A–Z'] as $val => $lbl): ?>
      <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $lbl ?></option>
    <?php endforeach; ?>
  </select>
  <button class="rounded-full bg-stone-900 text-white px-5 py-2.5 text-sm hover:bg-stone-800 transition">Apply</button>
</form>

<div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
  <p class="text-sm text-stone-500">
    <?php if ($totalFiltered === 0): ?>
      0 results
    <?php else: ?>
      Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalFiltered)) ?> of <?= number_format($totalFiltered) ?>
    <?php endif; ?>
  </p>
  <?php if ($hasFilters): ?>
    <a href="products.php" class="text-sm text-stone-500 hover:text-stone-900 flex items-center gap-1"><?= admin_icon('x', 'w-4 h-4') ?> Clear filters</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[900px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Product</th>
        <th class="px-4 py-3">Category</th>
        <th class="px-4 py-3">Price</th>
        <th class="px-4 py-3">Stock</th>
        <th class="px-4 py-3">Sold</th>
        <th class="px-4 py-3">Revenue</th>
        <th class="px-4 py-3">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pageRows as $p): ?>
        <?php $stock = (int) $p['stock_total']; ?>
        <tr class="border-t border-stone-100 hover:bg-stone-50/60">
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <?php if (!empty($p['image']) && file_exists(ROOT_PATH . '/' . $p['image'])): ?>
                <img src="<?= e(asset($p['image'])) ?>" class="w-11 h-11 rounded-lg object-cover shrink-0" alt="">
              <?php else: ?>
                <span class="w-11 h-11 rounded-lg bg-stone-100 flex items-center justify-center text-stone-300 shrink-0"><?= admin_icon('image', 'w-5 h-5') ?></span>
              <?php endif; ?>
              <div class="min-w-0">
                <a href="product-edit.php?id=<?= (int) $p['id'] ?>" class="font-medium hover:underline block truncate max-w-[220px]"><?= e($p['name']) ?></a>
                <span class="text-xs text-stone-400">#<?= (int) $p['id'] ?></span>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-stone-600"><?= e($p['category_name'] ?? '—') ?></td>
          <td class="px-4 py-3">
            &pound;<?= number_format((float) $p['base_price'], 2) ?>
            <?php if (!empty($p['compare_at_price']) && (float) $p['compare_at_price'] > (float) $p['base_price']): ?>
              <span class="text-xs text-stone-400 line-through block">&pound;<?= number_format((float) $p['compare_at_price'], 2) ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($stock === 0): ?>
              <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs bg-rose-100 text-rose-700">Out</span>
            <?php elseif ($stock <= 5): ?>
              <span class="inline-flex items-center gap-1 text-amber-600 font-semibold"><?= $stock ?> <?= admin_icon('alert-triangle', 'w-3.5 h-3.5') ?></span>
            <?php else: ?>
              <span class="text-stone-700"><?= $stock ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <span class="font-medium tabular-nums"><?= number_format($p['units_sold']) ?></span>
              <?php if ($maxSold > 0 && $p['units_sold'] > 0): ?>
                <span class="hidden lg:block w-16 h-1.5 bg-stone-100 rounded-full overflow-hidden"><span class="block h-full bg-emerald-400" style="width: <?= round($p['units_sold'] / $maxSold * 100) ?>%"></span></span>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-3 text-stone-600 tabular-nums">&pound;<?= number_format($p['revenue'], 2) ?></td>
          <td class="px-4 py-3">
            <div class="flex flex-wrap gap-1">
              <?php if ($p['is_active']): ?>
                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-100 text-emerald-800">Active</span>
              <?php else: ?>
                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-stone-200 text-stone-600">Hidden</span>
              <?php endif; ?>
              <?php if ($p['is_featured']): ?>
                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800">Featured</span>
              <?php endif; ?>
              <?php if (!empty($p['is_new'])): ?>
                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-stone-900 text-white">New</span>
              <?php endif; ?>
              <?php if ($p['on_sale']): ?>
                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium bg-red-600 text-white">Sale</span>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1">
              <a class="p-1.5 rounded-lg text-stone-500 hover:text-stone-900 hover:bg-stone-100" title="Edit" href="product-edit.php?id=<?= (int) $p['id'] ?>"><?= admin_icon('pencil') ?></a>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="feature">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="return_q" value="<?= e($q) ?>">
                <input type="hidden" name="return_cat" value="<?= (int) $catFilter ?>">
                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="return_sort" value="<?= e($sort) ?>">
                <input type="hidden" name="return_page" value="<?= (int) $page ?>">
                <button class="p-1.5 rounded-lg <?= $p['is_featured'] ? 'text-amber-500 hover:bg-amber-50' : 'text-stone-400 hover:text-amber-500 hover:bg-stone-100' ?>" title="Toggle featured"><?= admin_icon('star') ?></button>
              </form>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="return_q" value="<?= e($q) ?>">
                <input type="hidden" name="return_cat" value="<?= (int) $catFilter ?>">
                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="return_sort" value="<?= e($sort) ?>">
                <input type="hidden" name="return_page" value="<?= (int) $page ?>">
                <button class="p-1.5 rounded-lg text-stone-500 hover:text-stone-900 hover:bg-stone-100" title="Toggle active"><?= admin_icon('eye') ?></button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Delete product?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="return_q" value="<?= e($q) ?>">
                <input type="hidden" name="return_cat" value="<?= (int) $catFilter ?>">
                <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="return_sort" value="<?= e($sort) ?>">
                <input type="hidden" name="return_page" value="<?= (int) $page ?>">
                <button class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50" title="Delete"><?= admin_icon('trash-2') ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pageRows): ?>
        <tr><td colspan="8" class="px-4 py-10 text-center text-stone-400"><?= $hasFilters ? 'No products match your filters' : 'No products yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
    <p class="text-sm text-stone-500">Page <?= $page ?> of <?= $totalPages ?></p>
    <div class="flex items-center gap-1.5">
      <?php if ($page > 1): ?>
        <a href="<?= e(products_page_url($filterQs, $page - 1)) ?>" class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-white px-3.5 py-2 text-sm text-stone-600 hover:bg-stone-50 transition">
          <?= admin_icon('chevron-left', 'w-4 h-4') ?> Prev
        </a>
      <?php else: ?>
        <span class="inline-flex items-center gap-1 rounded-full border border-stone-100 bg-stone-50 px-3.5 py-2 text-sm text-stone-300 cursor-not-allowed">
          <?= admin_icon('chevron-left', 'w-4 h-4') ?> Prev
        </span>
      <?php endif; ?>

      <?php
      $window = 2;
      $start = max(1, $page - $window);
      $end = min($totalPages, $page + $window);
      if ($start > 1): ?>
        <a href="<?= e(products_page_url($filterQs, 1)) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-full border border-stone-200 bg-white text-sm hover:bg-stone-50">1</a>
        <?php if ($start > 2): ?><span class="px-1 text-stone-400 text-sm">…</span><?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="w-9 h-9 inline-flex items-center justify-center rounded-full bg-stone-900 text-white text-sm font-medium"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= e(products_page_url($filterQs, $i)) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-full border border-stone-200 bg-white text-sm hover:bg-stone-50"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?><span class="px-1 text-stone-400 text-sm">…</span><?php endif; ?>
        <a href="<?= e(products_page_url($filterQs, $totalPages)) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-full border border-stone-200 bg-white text-sm hover:bg-stone-50"><?= $totalPages ?></a>
      <?php endif; ?>

      <?php if ($page < $totalPages): ?>
        <a href="<?= e(products_page_url($filterQs, $page + 1)) ?>" class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-white px-3.5 py-2 text-sm text-stone-600 hover:bg-stone-50 transition">
          Next <?= admin_icon('chevron-right', 'w-4 h-4') ?>
        </a>
      <?php else: ?>
        <span class="inline-flex items-center gap-1 rounded-full border border-stone-100 bg-stone-50 px-3.5 py-2 text-sm text-stone-300 cursor-not-allowed">
          Next <?= admin_icon('chevron-right', 'w-4 h-4') ?>
        </span>
      <?php endif; ?>
    </div>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
