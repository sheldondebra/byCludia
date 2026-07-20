<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'delete' && $id) {
        db()->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    } elseif ($action === 'save') {
        $name = trim((string) post('name'));
        $slug = trim((string) post('slug')) ?: slugify($name);
        $desc = trim((string) post('description'));
        $sort = (int) post('sort_order');
        if ($name !== '') {
            if ($id) {
                db()->prepare('UPDATE categories SET name=?, slug=?, description=?, sort_order=? WHERE id=?')
                    ->execute([$name, $slug, $desc, $sort, $id]);
            } else {
                db()->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?,?,?,?)')
                    ->execute([$name, $slug, $desc, $sort]);
            }
            flash('success', 'Category saved.');
        } else {
            flash('error', 'Name is required.');
        }
    }
    header('Location: categories.php');
    exit;
}

$editId = (int) get('edit', 0);
$editing = null;
if ($editId) {
    $e = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $e->execute([$editId]);
    $editing = $e->fetch() ?: null;
}

$categories = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.sort_order, c.name'
)->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-2">Categories</h1>
<p class="text-sm text-stone-500 mb-6">Organise products into shop collections.</p>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-4 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-[340px_1fr] gap-6 items-start">
  <form method="post" class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="font-medium"><?= $editing ? 'Edit category' : 'New category' ?></h2>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Name</label>
      <input name="name" required value="<?= e($editing['name'] ?? '') ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Slug</label>
      <input name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="auto from name" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Description</label>
      <textarea name="description" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($editing['description'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Sort order</label>
      <input name="sort_order" type="number" value="<?= e((string) ($editing['sort_order'] ?? '0')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div class="flex gap-2">
      <button class="flex-1 rounded-full bg-stone-900 text-white px-6 py-2.5 text-sm hover:bg-stone-800 transition"><?= $editing ? 'Update' : 'Create' ?></button>
      <?php if ($editing): ?><a href="categories.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100">Cancel</a><?php endif; ?>
    </div>
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm min-w-[520px]">
      <thead class="bg-stone-50 text-left text-stone-500">
        <tr>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Slug</th>
          <th class="px-4 py-3">Products</th>
          <th class="px-4 py-3">Sort</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr class="border-t border-stone-100">
            <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
            <td class="px-4 py-3 text-stone-500"><?= e($c['slug']) ?></td>
            <td class="px-4 py-3 text-stone-500"><?= (int) $c['product_count'] ?></td>
            <td class="px-4 py-3 text-stone-500"><?= (int) $c['sort_order'] ?></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-3">
                <a class="text-stone-500 hover:text-stone-900" href="categories.php?edit=<?= (int) $c['id'] ?>" title="Edit"><?= admin_icon('pencil') ?></a>
                <a class="text-stone-500 hover:text-stone-900" href="<?= e(url('index.php?page=shop&category=' . urlencode($c['slug']))) ?>" target="_blank" title="View"><?= admin_icon('external-link') ?></a>
                <form method="post" class="inline" onsubmit="return confirm('Delete category? Products will be uncategorised.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="text-rose-500 hover:text-rose-700" title="Delete"><?= admin_icon('trash-2') ?></button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?><tr><td colspan="5" class="px-4 py-8 text-center text-stone-400">No categories yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
