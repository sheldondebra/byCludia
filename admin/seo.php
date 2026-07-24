<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

function seo_admin_setting_save(string $key, string $val): void
{
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')
            ->execute([$key, $val]);
    } else {
        db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
            ->execute([$key, $val]);
    }
}

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    foreach (['seo_title_pattern', 'meta_description', 'og_image', 'store_address', 'store_city', 'store_country'] as $key) {
        seo_admin_setting_save($key, trim((string) post($key, '')));
    }
    flash('success', 'SEO settings saved.');
    header('Location: seo.php');
    exit;
}

$tab = preg_replace('/[^a-z]/', '', strtolower((string) get('tab', 'pages'))) ?: 'pages';
if (!in_array($tab, ['pages', 'products', 'categories', 'blog'], true)) {
    $tab = 'pages';
}

$store = seo_store_name();
$pattern = seo_title_pattern();
$metaDesc = (string) setting('meta_description', 'Luxury hair for every curl story.');
$ogImage = (string) setting('og_image', setting('logo_path', 'assets/images/logo.png'));
$homeTitle = seo_format_title($store);
$homeScore = seo_score([
    'title' => $homeTitle,
    'description' => $metaDesc,
    'image' => $ogImage,
    'has_schema' => true,
]);

$staticPages = [
    ['name' => 'Home', 'url' => url(), 'title' => $homeTitle, 'description' => $metaDesc, 'image' => $ogImage, 'edit' => 'seo.php', 'has_schema' => true],
    ['name' => 'Shop', 'url' => url('shop'), 'title' => seo_format_title('Shop'), 'description' => 'Shop premium wigs, bundles, closures and crochet hair.', 'image' => $ogImage, 'edit' => null, 'has_schema' => true],
    ['name' => 'About', 'url' => url('about'), 'title' => seo_format_title('Our Story'), 'description' => $metaDesc, 'image' => $ogImage, 'edit' => 'settings.php', 'has_schema' => true],
    ['name' => 'Journal', 'url' => url('blog'), 'title' => seo_format_title('Journal'), 'description' => $metaDesc, 'image' => $ogImage, 'edit' => null, 'has_schema' => true],
    ['name' => 'FAQ', 'url' => url('faq'), 'title' => seo_format_title('FAQ'), 'description' => $metaDesc, 'image' => $ogImage, 'edit' => null, 'has_schema' => true],
    ['name' => 'Contact', 'url' => url('contact'), 'title' => seo_format_title('Contact'), 'description' => $metaDesc, 'image' => $ogImage, 'edit' => 'settings.php', 'has_schema' => true],
    ['name' => 'Gift cards', 'url' => url('gift-cards'), 'title' => seo_format_title('Gift Cards'), 'description' => $metaDesc, 'image' => $ogImage, 'edit' => null, 'has_schema' => true],
];

$products = db()->query('SELECT id, name, slug, image, short_description, seo_title, seo_description, focus_keyword, faq_json FROM products ORDER BY name')->fetchAll();
$categories = db()->query('SELECT id, name, slug, description, seo_title, seo_description, focus_keyword, intro_html FROM categories ORDER BY sort_order, name')->fetchAll();
$posts = [];
try {
    $posts = db()->query('SELECT id, title, slug, excerpt, image, seo_title, seo_description, focus_keyword FROM blog_posts ORDER BY published_at DESC')->fetchAll();
} catch (Throwable $e) {
}

$missingTitle = 0;
$missingDesc = 0;
$missingImage = 0;
foreach ($products as $p) {
    $t = trim((string) ($p['seo_title'] ?? '')) ?: (string) $p['name'];
    $d = trim((string) ($p['seo_description'] ?? '')) ?: (string) ($p['short_description'] ?? '');
    if (mb_strlen($t) < 30 || mb_strlen($t) > 60) {
        $missingTitle++;
    }
    if (mb_strlen($d) < 70 || mb_strlen($d) > 160) {
        $missingDesc++;
    }
    if (empty($p['image'])) {
        $missingImage++;
    }
}

$sitemapUrl = url('sitemap.xml');
$scoreBadge = static function (int $score): string {
    if ($score >= 80) {
        return 'bg-emerald-50 text-emerald-700';
    }
    if ($score >= 50) {
        return 'bg-amber-50 text-amber-800';
    }
    return 'bg-rose-50 text-rose-700';
};

require __DIR__ . '/_layout_top.php';
?>

<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
  <div>
    <h1 class="font-display text-4xl mb-1">SEO</h1>
    <p class="text-sm text-stone-500">Titles, previews, checklist scores, and Search Console links.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= e($sitemapUrl) ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 bg-white px-4 py-2 text-xs hover:bg-stone-50"><?= admin_icon('external-link', 'w-3.5 h-3.5') ?> Sitemap</a>
    <button type="button" id="copy-sitemap" data-url="<?= e($sitemapUrl) ?>" class="inline-flex items-center gap-1.5 rounded-full bg-stone-900 text-white px-4 py-2 text-xs hover:bg-stone-800"><?= admin_icon('copy', 'w-3.5 h-3.5') ?> Copy sitemap URL</button>
  </div>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= e($msg) ?></div><?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6 mb-8">
  <form method="post" class="lg:col-span-2 bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
    <?= csrf_field() ?>
    <h2 class="font-medium">Site defaults</h2>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Title pattern</label>
      <input name="seo_title_pattern" value="<?= e($pattern) ?>" placeholder="{page} | {store}" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      <p class="text-[11px] text-stone-400 mt-1">Use <code>{page}</code> and <code>{store}</code>.</p>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Default meta description</label>
      <textarea name="meta_description" rows="2" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($metaDesc) ?></textarea>
    </div>
    <div>
      <label class="text-xs text-stone-500 mb-1 block">Default OG image path</label>
      <input name="og_image" value="<?= e($ogImage) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
    </div>
    <div class="grid sm:grid-cols-3 gap-3">
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Address</label>
        <input name="store_address" value="<?= e((string) setting('store_address', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">City</label>
        <input name="store_city" value="<?= e((string) setting('store_city', '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Country</label>
        <input name="store_country" value="<?= e((string) setting('store_country', '')) ?>" placeholder="GB" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm">
      </div>
    </div>
    <button class="rounded-full bg-stone-900 text-white px-6 py-2.5 text-sm hover:bg-stone-800">Save defaults</button>
  </form>

  <div class="space-y-4">
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <p class="text-xs uppercase tracking-wider text-stone-400 mb-2">Homepage Google preview</p>
      <p class="text-[#1a0dab] text-lg leading-snug"><?= e($homeTitle) ?></p>
      <p class="text-[#006621] text-xs truncate"><?= e(url()) ?></p>
      <p class="text-sm text-stone-600 mt-1"><?= e(mb_substr($metaDesc, 0, 160)) ?></p>
      <p class="mt-3 inline-flex text-xs font-medium px-2.5 py-1 rounded-full <?= e($scoreBadge($homeScore)) ?>">Score <?= $homeScore ?>/100</p>
    </div>
    <div class="bg-white rounded-2xl border border-stone-200 p-5 grid grid-cols-3 gap-3 text-center">
      <div>
        <p class="text-2xl font-semibold"><?= $missingTitle ?></p>
        <p class="text-[11px] text-stone-500">Title issues</p>
      </div>
      <div>
        <p class="text-2xl font-semibold"><?= $missingDesc ?></p>
        <p class="text-[11px] text-stone-500">Desc issues</p>
      </div>
      <div>
        <p class="text-2xl font-semibold"><?= $missingImage ?></p>
        <p class="text-[11px] text-stone-500">No image</p>
      </div>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
  <div class="flex flex-wrap gap-1 border-b border-stone-100 p-2">
    <?php foreach (['pages' => 'Pages', 'products' => 'Products', 'categories' => 'Categories', 'blog' => 'Blog'] as $key => $label): ?>
      <a href="?tab=<?= $key ?>" class="px-4 py-2 rounded-full text-xs tracking-wide uppercase <?= $tab === $key ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-50' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[720px]">
      <thead class="bg-stone-50 text-left text-stone-500">
        <tr>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Title len</th>
          <th class="px-4 py-3">Desc len</th>
          <th class="px-4 py-3">Score</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rows = [];
        if ($tab === 'pages') {
            foreach ($staticPages as $p) {
                $rows[] = [
                    'name' => $p['name'],
                    'title' => $p['title'],
                    'description' => $p['description'],
                    'image' => $p['image'],
                    'has_schema' => $p['has_schema'],
                    'edit' => $p['edit'],
                    'url' => $p['url'],
                ];
            }
        } elseif ($tab === 'products') {
            foreach ($products as $p) {
                $title = trim((string) ($p['seo_title'] ?? '')) ?: (string) $p['name'];
                $desc = trim((string) ($p['seo_description'] ?? '')) ?: (string) ($p['short_description'] ?? '');
                $rows[] = [
                    'name' => $p['name'],
                    'title' => seo_format_title($title),
                    'description' => $desc,
                    'image' => (string) ($p['image'] ?? ''),
                    'focus_keyword' => (string) ($p['focus_keyword'] ?? ''),
                    'has_schema' => true,
                    'edit' => 'product-edit.php?id=' . (int) $p['id'],
                    'url' => url('product/' . $p['slug']),
                ];
            }
        } elseif ($tab === 'categories') {
            foreach ($categories as $c) {
                $title = trim((string) ($c['seo_title'] ?? '')) ?: (string) $c['name'];
                $desc = trim((string) ($c['seo_description'] ?? '')) ?: (string) ($c['description'] ?? '');
                $rows[] = [
                    'name' => $c['name'],
                    'title' => seo_format_title($title),
                    'description' => $desc,
                    'image' => $ogImage,
                    'focus_keyword' => (string) ($c['focus_keyword'] ?? ''),
                    'has_schema' => true,
                    'edit' => 'categories.php?edit=' . (int) $c['id'],
                    'url' => url('shop?category=' . rawurlencode((string) $c['slug'])),
                ];
            }
        } else {
            foreach ($posts as $p) {
                $title = trim((string) ($p['seo_title'] ?? '')) ?: (string) $p['title'];
                $desc = trim((string) ($p['seo_description'] ?? '')) ?: (string) ($p['excerpt'] ?? '');
                $rows[] = [
                    'name' => $p['title'],
                    'title' => seo_format_title($title),
                    'description' => $desc,
                    'image' => (string) ($p['image'] ?? ''),
                    'focus_keyword' => (string) ($p['focus_keyword'] ?? ''),
                    'has_schema' => true,
                    'edit' => null,
                    'url' => url('blog/' . $p['slug']),
                ];
            }
        }
        ?>
        <?php foreach ($rows as $row):
            $score = seo_score([
                'title' => $row['title'],
                'description' => $row['description'],
                'image' => $row['image'] ?? '',
                'focus_keyword' => $row['focus_keyword'] ?? '',
                'has_schema' => !empty($row['has_schema']),
            ]);
            ?>
          <tr class="border-t border-stone-100">
            <td class="px-4 py-3">
              <p class="font-medium"><?= e($row['name']) ?></p>
              <p class="text-xs text-stone-400 truncate max-w-xs"><?= e($row['url']) ?></p>
            </td>
            <td class="px-4 py-3 text-stone-500"><?= mb_strlen((string) $row['title']) ?></td>
            <td class="px-4 py-3 text-stone-500"><?= mb_strlen((string) $row['description']) ?></td>
            <td class="px-4 py-3"><span class="inline-flex text-xs font-medium px-2.5 py-1 rounded-full <?= e($scoreBadge($score)) ?>"><?= $score ?></span></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <a href="<?= e($row['url']) ?>" target="_blank" class="text-stone-500 hover:text-stone-900" title="View"><?= admin_icon('external-link') ?></a>
              <?php if (!empty($row['edit'])): ?>
                <a href="<?= e($row['edit']) ?>" class="text-stone-500 hover:text-stone-900 ml-2" title="Edit"><?= admin_icon('pencil') ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="px-4 py-8 text-center text-stone-400">Nothing here yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('copy-sitemap')?.addEventListener('click', function () {
  const url = this.getAttribute('data-url');
  const done = () => { this.textContent = 'Copied!'; setTimeout(() => { this.innerHTML = 'Copy sitemap URL'; }, 1500); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(() => window.prompt('Copy sitemap URL', url));
  } else {
    window.prompt('Copy sitemap URL', url);
  }
});
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
