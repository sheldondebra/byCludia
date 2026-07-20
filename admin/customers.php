<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

$q = trim((string) get('q', ''));
$sql = "SELECT u.id, u.name, u.email, u.loyalty_points, u.created_at, "
     . "(SELECT COUNT(*) FROM orders o WHERE o.email = u.email) AS order_count, "
     . "(SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.email = u.email AND o.status IN ('paid','processing','shipped','delivered')) AS total_spent "
     . "FROM users u WHERE u.role = 'customer'";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sql .= ' ORDER BY u.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require __DIR__ . '/_layout_top.php';
?>

<h1 class="font-display text-4xl mb-8">Customers</h1>

<form method="get" class="mb-6 max-w-md relative">
  <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
  <input name="q" value="<?= e($q) ?>" placeholder="Search name or email…"
    class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
</form>

<div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
  <table class="w-full text-sm min-w-[720px]">
    <thead class="bg-stone-50 text-left text-stone-500">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th class="px-4 py-3">Email</th>
        <th class="px-4 py-3">Orders</th>
        <th class="px-4 py-3">Total spent (GBP)</th>
        <th class="px-4 py-3">Points</th>
        <th class="px-4 py-3">Joined</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="border-t border-stone-100">
          <td class="px-4 py-3 font-medium"><?= e($c['name']) ?></td>
          <td class="px-4 py-3">
            <a class="underline text-stone-600" href="orders.php?q=<?= urlencode((string) $c['email']) ?>"><?= e($c['email']) ?></a>
          </td>
          <td class="px-4 py-3"><?= (int) $c['order_count'] ?></td>
          <td class="px-4 py-3">&pound;<?= number_format((float) $c['total_spent'], 2) ?></td>
          <td class="px-4 py-3"><?= (int) $c['loyalty_points'] ?></td>
          <td class="px-4 py-3 text-stone-500"><?= e($c['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400"><?= $q !== '' ? 'No customers match your search' : 'No customers yet' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
