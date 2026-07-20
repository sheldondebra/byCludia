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

$related = db()->prepare('SELECT title, slug, excerpt, published_at FROM blog_posts WHERE is_published = 1 AND id <> ? ORDER BY published_at DESC, id DESC LIMIT 3');
$related->execute([(int) $post['id']]);
$relatedPosts = $related->fetchAll();

$pageTitle = $post['title'] . ' – Hair by Claudia Darlene';
$pageDescription = $post['excerpt'] ?? $post['title'];

// --- SEO ---
$canonical = url('blog/' . $post['slug']);
$ogType = 'article';
if (!empty($post['image'])) {
    $ogImage = $post['image'];
}
$publishedIso = !empty($post['published_at']) ? date('c', (int) strtotime((string) $post['published_at'])) : null;
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => strip_tags((string) ($post['excerpt'] ?? '')),
    'image' => !empty($post['image']) ? asset($post['image']) : asset((string) setting('logo_path', 'assets/images/logo.png')),
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
    'author' => ['@type' => 'Organization', 'name' => setting('store_name', 'By Claudia Darlene')],
    'publisher' => [
        '@type' => 'Organization',
        'name' => setting('store_name', 'By Claudia Darlene'),
        'logo' => ['@type' => 'ImageObject', 'url' => asset((string) setting('logo_path', 'assets/images/logo.png'))],
    ],
    'datePublished' => $publishedIso,
    'dateModified' => $publishedIso,
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
      <div class="aspect-[16/9] rounded-3xl overflow-hidden mb-8">
        <img src="<?= e(asset((string) $post['image'])) ?>" alt="<?= e($post['title']) ?>" class="w-full h-full object-cover">
      </div>
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

  <?php if ($relatedPosts): ?>
    <div class="max-w-6xl mx-auto px-6 mt-16 sm:mt-20">
      <h2 class="font-display text-3xl mb-8 text-center">Keep reading</h2>
      <div class="grid gap-8 sm:grid-cols-3">
        <?php foreach ($relatedPosts as $r): ?>
          <?php $rlink = e(url('index.php?page=blog-post&slug=' . urlencode((string) $r['slug']))); ?>
          <a href="<?= $rlink ?>" class="block bg-white/70 border border-brand-ink/5 rounded-3xl p-6 hover:shadow-soft transition">
            <?php if (!empty($r['published_at'])): ?>
              <p class="text-[11px] tracking-[0.16em] uppercase text-brand-soft mb-2"><?= e(date('j M Y', (int) strtotime((string) $r['published_at']))) ?></p>
            <?php endif; ?>
            <h3 class="font-display text-xl mb-2 leading-snug"><?= e($r['title']) ?></h3>
            <p class="text-sm text-brand-soft"><?= e($r['excerpt'] ?? '') ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</article>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
