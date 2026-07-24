<?php
declare(strict_types=1);

$pageTitle = 'Journal – Hair Care & Texture Guides | By Claudia Darlene';
$pageDescription = 'SEO guides on washing virgin hair extensions, keeping curly and coily textures soft, night care, and choosing By Claudia Darlene bundles, units, clip-ins, and crochet.';
$canonical = url('blog');
$posts = db()->query('SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC, id DESC')->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-6xl mx-auto px-6">
    <h1 class="font-display text-5xl text-center mb-4">Journal</h1>
    <p class="text-center text-brand-soft mb-12 max-w-2xl mx-auto">Hair-care rituals and product guides for virgin textured extensions — wash days, night protection, and finding your perfect match in Our Collection. Photos from <a class="underline hover:text-brand-ink" href="<?= e((string) setting('social_instagram', 'https://www.instagram.com/byclaudiadarlene/')) ?>" target="_blank" rel="noopener">@byclaudiadarlene</a>.</p>

    <?php if (!$posts): ?>
      <div class="text-center bg-white/70 rounded-3xl border border-brand-ink/5 p-10">
        <p class="text-brand-soft mb-2">Posts coming soon.</p>
      </div>
    <?php else: ?>
      <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($posts as $post): ?>
          <?php $link = e(url('index.php?page=blog-post&slug=' . urlencode((string) $post['slug']))); ?>
          <article class="group bg-white/70 border border-brand-ink/5 rounded-3xl overflow-hidden hover:shadow-soft transition">
            <a href="<?= $link ?>" class="block">
              <?php if (!empty($post['image'])): ?>
                <div class="aspect-[16/10] overflow-hidden">
                  <img src="<?= e(asset((string) $post['image'])) ?>" alt="<?= e($post['title']) ?>" class="blog-photo w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
              <?php else: ?>
                <div class="aspect-[16/10] bg-gradient-to-br from-brand-mist via-brand-blush/60 to-[#e8c4a8] flex items-end p-5">
                  <span class="font-display text-2xl text-brand-ink/70 leading-tight"><?= e($post['title']) ?></span>
                </div>
              <?php endif; ?>
            </a>
            <div class="p-6">
              <?php if (!empty($post['published_at'])): ?>
                <p class="text-[11px] tracking-[0.16em] uppercase text-brand-soft mb-2"><?= e(date('j M Y', (int) strtotime((string) $post['published_at']))) ?></p>
              <?php endif; ?>
              <h2 class="font-display text-2xl mb-2 leading-snug"><a href="<?= $link ?>" class="hover:opacity-70"><?= e($post['title']) ?></a></h2>
              <p class="text-sm text-brand-soft mb-4"><?= e($post['excerpt'] ?? '') ?></p>
              <a href="<?= $link ?>" class="text-sm tracking-[0.14em] uppercase border-b border-brand-ink/30 pb-0.5 hover:border-brand-ink">Read more</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
