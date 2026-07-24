<?php
declare(strict_types=1);

$slug = trim((string) get('slug', ''));
$stmt = db()->prepare('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1 LIMIT 1');
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    flash('error', 'Article not found.');
    redirect('index.php?page=blog');
}

$related = db()->prepare('SELECT title, slug, excerpt, image, published_at FROM blog_posts WHERE is_published = 1 AND id <> ? ORDER BY published_at DESC, id DESC LIMIT 3');
$related->execute([(int) $post['id']]);
$relatedPosts = $related->fetchAll();

$relatedProducts = [];
$relatedIds = json_decode((string) ($post['related_product_ids'] ?? ''), true);
if (is_array($relatedIds) && $relatedIds) {
    $relatedIds = array_values(array_filter(array_map('intval', $relatedIds)));
    if ($relatedIds) {
        $placeholders = implode(',', array_fill(0, count($relatedIds), '?'));
        $pStmt = db()->prepare("SELECT * FROM products WHERE is_active = 1 AND id IN ($placeholders)");
        $pStmt->execute($relatedIds);
        $byId = [];
        foreach ($pStmt->fetchAll() as $row) {
            $byId[(int) $row['id']] = $row;
        }
        foreach ($relatedIds as $rid) {
            if (isset($byId[$rid])) {
                $relatedProducts[] = $byId[$rid];
            }
        }
    }
}

$igUrl = (string) setting('social_instagram', 'https://www.instagram.com/byclaudiadarlene/');

$pageTitle = $post['title'] . ' – Hair by Claudia Darlene';
$pageDescription = $post['excerpt'] ?? $post['title'];

// --- SEO ---
$canonical = url('blog/' . $post['slug']);
$seoTitle = trim((string) ($post['seo_title'] ?? ''));
$seoDesc = trim((string) ($post['seo_description'] ?? ''));
$pageTitle = seo_format_title($seoTitle !== '' ? $seoTitle : (string) $post['title']);
$pageDescription = $seoDesc !== '' ? $seoDesc : (string) ($post['excerpt'] ?? $post['title']);
$ogType = 'article';
$ogImageAlt = (string) $post['title'];
if (!empty($post['image'])) {
    $ogImage = $post['image'];
}
$publishedIso = !empty($post['published_at']) ? date('c', (int) strtotime((string) $post['published_at'])) : null;
$articleLd = [
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => strip_tags((string) ($pageDescription)),
    'image' => !empty($post['image']) ? asset($post['image']) : asset((string) setting('logo_path', 'assets/images/logo.png')),
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
    'author' => ['@type' => 'Organization', 'name' => seo_store_name()],
    'publisher' => [
        '@type' => 'Organization',
        'name' => seo_store_name(),
        'logo' => ['@type' => 'ImageObject', 'url' => asset((string) setting('logo_path', 'assets/images/logo.png'))],
    ],
];
if ($publishedIso) {
    $articleLd['datePublished'] = $publishedIso;
    $articleLd['dateModified'] = $publishedIso;
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $articleLd,
        seo_breadcrumbs([
            ['name' => 'Home', 'url' => url()],
            ['name' => 'Journal', 'url' => url('blog')],
            ['name' => (string) $post['title'], 'url' => $canonical],
        ]),
    ],
];

require ROOT_PATH . '/includes/header.php';
?>

<article class="py-14 sm:py-20">
  <div class="max-w-3xl mx-auto px-6">
    <a href="<?= e(url('index.php?page=blog')) ?>" class="text-sm tracking-[0.14em] uppercase text-brand-soft hover:text-brand-ink">← Journal</a>

    <?php if (!empty($post['published_at'])): ?>
      <p class="text-[11px] tracking-[0.16em] uppercase text-brand-soft mt-8 mb-3"><?= e(date('j M Y', (int) strtotime((string) $post['published_at']))) ?></p>
    <?php endif; ?>
    <h1 class="font-display text-4xl sm:text-5xl leading-tight mb-6"><?= e($post['title']) ?></h1>

    <?php if (!empty($post['image'])): ?>
      <div class="aspect-[16/9] rounded-3xl overflow-hidden mb-3">
        <img src="<?= e(asset((string) $post['image'])) ?>" alt="<?= e($post['title']) ?>" class="blog-photo w-full h-full object-cover">
      </div>
      <p class="text-xs text-brand-soft mb-8">
        Photo from
        <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener" class="underline hover:text-brand-ink">@byclaudiadarlene</a>
        on Instagram
      </p>
    <?php else: ?>
      <div class="aspect-[16/7] rounded-3xl bg-gradient-to-br from-brand-mist via-brand-blush/60 to-[#e8c4a8] mb-8"></div>
    <?php endif; ?>

    <div class="prose-blog text-brand-ink/80 leading-relaxed space-y-5 text-[17px]">
      <?= $post['body'] ?>
    </div>

    <div class="mt-10 pt-6 border-t border-brand-ink/10">
      <?php
      $shareUrl = $canonical;
      $shareTitle = $post['title'];
      $shareImage = !empty($post['image']) ? asset((string) $post['image']) : '';
      require ROOT_PATH . '/includes/partials/share.php';
      ?>
    </div>
  </div>

  <?php if ($relatedProducts): ?>
    <div class="max-w-6xl mx-auto px-6 mt-16 sm:mt-20">
      <div class="text-center mb-8">
        <p class="text-[11px] tracking-[0.2em] uppercase text-brand-soft mb-2">Shop the story</p>
        <h2 class="font-display text-3xl sm:text-4xl">Related products</h2>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-7">
        <?php foreach ($relatedProducts as $product): ?>
          <?php require ROOT_PATH . '/includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-8">
        <a href="<?= e(url('index.php?page=shop')) ?>" class="inline-block btn-ink px-7 py-3 text-sm tracking-[0.12em] uppercase">Browse Our Collection</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($relatedPosts): ?>
    <div class="max-w-6xl mx-auto px-6 mt-16 sm:mt-20">
      <h2 class="font-display text-3xl mb-8 text-center">Keep reading</h2>
      <div class="grid gap-8 sm:grid-cols-3">
        <?php foreach ($relatedPosts as $r): ?>
          <?php $rlink = e(url('index.php?page=blog-post&slug=' . urlencode((string) $r['slug']))); ?>
          <a href="<?= $rlink ?>" class="group block bg-white/70 border border-brand-ink/5 rounded-3xl overflow-hidden hover:shadow-soft transition">
            <?php if (!empty($r['image']) && file_exists(ROOT_PATH . '/' . $r['image'])): ?>
              <div class="aspect-[16/10] overflow-hidden">
                <img src="<?= e(asset((string) $r['image'])) ?>" alt="<?= e($r['title']) ?>" class="blog-photo w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy">
              </div>
            <?php endif; ?>
            <div class="p-6">
              <?php if (!empty($r['published_at'])): ?>
                <p class="text-[11px] tracking-[0.16em] uppercase text-brand-soft mb-2"><?= e(date('j M Y', (int) strtotime((string) $r['published_at']))) ?></p>
              <?php endif; ?>
              <h3 class="font-display text-xl mb-2 leading-snug"><?= e($r['title']) ?></h3>
              <p class="text-sm text-brand-soft"><?= e($r['excerpt'] ?? '') ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</article>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
