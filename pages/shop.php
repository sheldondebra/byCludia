<?php
declare(strict_types=1);

$pageTitle = 'Shop – Hair by Claudia Darlene';
$pageDescription = 'Shop premium wigs, bundles, closures and crochet hair by Claudia Darlene. Ethically sourced textures for every curl story.';
$canonical = url('shop');
$categorySlug = trim((string) get('category', ''));
$q = trim((string) get('q', ''));
$sort = (string) get('sort', 'featured');
$minRaw = get('min', '');
$maxRaw = get('max', '');
$minPrice = ($minRaw !== '' && $minRaw !== null) ? (float) $minRaw : null;
$maxPrice = ($maxRaw !== '' && $maxRaw !== null) ? (float) $maxRaw : null;

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();

$sql = "SELECT p.* FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1
          AND (p.image IS NULL OR (p.image NOT LIKE '%.svg' AND p.image NOT LIKE '%logo.png'))";
$params = [];
if ($categorySlug !== '') {
    $sql .= ' AND c.slug = ?';
    $params[] = $categorySlug;
}
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($minPrice !== null) {
    $sql .= ' AND p.base_price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $sql .= ' AND p.base_price <= ?';
    $params[] = $maxPrice;
}
$sql .= match ($sort) {
    'price_low' => ' ORDER BY p.base_price ASC',
    'price_high' => ' ORDER BY p.base_price DESC',
    'newest' => ' ORDER BY p.id DESC',
    'rating' => ' ORDER BY p.rating DESC, p.review_count DESC',
    'name' => ' ORDER BY p.name ASC',
    default => ' ORDER BY p.is_featured DESC, p.id ASC',
};
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$priceBounds = db()->query('SELECT MIN(base_price) mn, MAX(base_price) mx FROM products WHERE is_active = 1')->fetch();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-14 sm:py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-10 reveal">
      <h1 class="font-display text-5xl mb-3">Our Collection</h1>
      <p class="text-brand-soft">Crafted for texture, volume, and effortless beauty.</p>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
      <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="px-4 py-2 rounded-full text-xs tracking-[0.14em] uppercase <?= $categorySlug === '' ? 'bg-brand-ink text-white' : 'bg-white border border-brand-ink/10' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="<?= e(url('index.php?page=shop&category=' . urlencode($cat['slug']))) ?>" class="px-4 py-2 rounded-full text-xs tracking-[0.14em] uppercase <?= $categorySlug === $cat['slug'] ? 'bg-brand-ink text-white' : 'bg-white border border-brand-ink/10' ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <form method="get" class="flex gap-2">
          <input type="hidden" name="page" value="shop">
          <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
          <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search hair…" class="rounded-full border border-brand-ink/10 bg-white px-4 py-2 text-sm min-w-[180px] focus:outline-none focus:ring-2 focus:ring-brand-blush">
          <button class="btn-ink px-5 py-2 text-sm">Search</button>
        </form>
        <form method="get" class="flex gap-2 items-center">
          <input type="hidden" name="page" value="shop">
          <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
          <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
          <select name="sort" onchange="this.form.submit()" class="rounded-full border border-brand-ink/10 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
            <?php foreach (['featured' => 'Featured', 'newest' => 'Newest', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low', 'rating' => 'Top rated', 'name' => 'Name A–Z'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <form method="get" class="flex flex-wrap items-center gap-3 mb-8 text-sm">
      <input type="hidden" name="page" value="shop">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <span class="text-brand-soft">Price</span>
      <input type="number" name="min" value="<?= e((string) ($minPrice ?? '')) ?>" placeholder="<?= (int) ($priceBounds['mn'] ?? 0) ?>" class="w-24 rounded-full border border-brand-ink/10 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-brand-blush">
      <span class="text-brand-soft">to</span>
      <input type="number" name="max" value="<?= e((string) ($maxPrice ?? '')) ?>" placeholder="<?= (int) ceil((float) ($priceBounds['mx'] ?? 0)) ?>" class="w-24 rounded-full border border-brand-ink/10 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-brand-blush">
      <button class="rounded-full border border-brand-ink/15 px-4 py-2 hover:bg-brand-ink hover:text-white transition">Apply</button>
      <?php if ($minPrice !== null || $maxPrice !== null || $q !== '' || $sort !== 'featured'): ?>
        <a href="<?= e(url('index.php?page=shop' . ($categorySlug ? '&category=' . urlencode($categorySlug) : ''))) ?>" class="text-brand-soft underline">Reset</a>
      <?php endif; ?>
    </form>

    <?php if (!$products): ?>
      <p class="text-center text-brand-soft py-16">No products found.</p>
    <?php else: ?>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-7">
        <?php foreach ($products as $product): ?>
          <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
