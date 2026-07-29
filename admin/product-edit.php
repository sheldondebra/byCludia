<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();
require_admin();

/** Save an uploaded image, return relative path or null. */
function admin_save_upload(array $file): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
        return null;
    }
    $dir = ROOT_PATH . '/assets/images/products/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = 'p' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rel = 'assets/images/products/uploads/' . $name;
    if (@move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $rel)) {
        return $rel;
    }
    return null;
}

$id = (int) get('id', 0);
$product = null;
$variants = [];
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if ($product) {
        $v = db()->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY price');
        $v->execute([$id]);
        $variants = $v->fetchAll();
    }
}

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
$error = null;
$notice = null;

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $name = trim((string) post('name'));
    $slug = trim((string) post('slug')) ?: slugify($name);
    $categoryId = (int) post('category_id') ?: null;
    $short = trim((string) post('short_description'));
    $desc = trim((string) post('description'));
    $price = (float) post('base_price');
    $compareAt = post('compare_at_price') !== '' ? (float) post('compare_at_price') : null;
    $rating = (float) post('rating', 5);
    $reviewCount = (int) post('review_count', 0);
    $featured = post('is_featured') ? 1 : 0;
    $onSale = post('on_sale') ? 1 : 0;
    $isNew = post('is_new') ? 1 : 0;
    $active = post('is_active') ? 1 : 0;
    $seoTitle = mb_substr(trim((string) post('seo_title')), 0, 70);
    $seoDescription = mb_substr(trim((string) post('seo_description')), 0, 320);
    $focusKeyword = mb_substr(trim((string) post('focus_keyword')), 0, 80);
    $imageAlt = mb_substr(trim((string) post('image_alt')), 0, 160);
    $faqQs = (array) post('faq_q', []);
    $faqAs = (array) post('faq_a', []);
    $faqs = [];
    foreach ($faqQs as $i => $q) {
        $q = trim((string) $q);
        $a = trim((string) ($faqAs[$i] ?? ''));
        if ($q !== '' && $a !== '') {
            $faqs[] = ['question' => $q, 'answer' => $a];
        }
        if (count($faqs) >= 8) {
            break;
        }
    }
    $faqJson = $faqs ? json_encode($faqs, JSON_UNESCAPED_UNICODE) : null;

    // Existing gallery
    $gallery = [];
    if ($product && !empty($product['gallery'])) {
        $decoded = json_decode((string) $product['gallery'], true);
        if (is_array($decoded)) {
            $gallery = $decoded;
        }
    }
    // Remove selected gallery images
    $removed = (array) post('gallery_remove', []);
    if ($removed) {
        $gallery = array_values(array_filter($gallery, fn($g) => !in_array($g, $removed, true)));
    }
    // Featured image: existing or from field
    $image = $product['image'] ?? '';
    $imagePath = trim((string) post('image_path'));
    if ($imagePath !== '') {
        $image = $imagePath;
    }
    // Uploaded featured image
    if (!empty($_FILES['image_file'])) {
        $up = admin_save_upload($_FILES['image_file']);
        if ($up) {
            $image = $up;
        }
    }
    // Uploaded gallery images
    if (!empty($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['name'])) {
        foreach (array_keys($_FILES['gallery_files']['name']) as $k) {
            $up = admin_save_upload([
                'name' => $_FILES['gallery_files']['name'][$k],
                'tmp_name' => $_FILES['gallery_files']['tmp_name'][$k],
                'error' => $_FILES['gallery_files']['error'][$k],
            ]);
            if ($up) {
                $gallery[] = $up;
            }
        }
    }
    // Manual gallery paths (one per line)
    $galleryAdd = trim((string) post('gallery_add'));
    if ($galleryAdd !== '') {
        foreach (preg_split('/\r?\n/', $galleryAdd) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $gallery[] = $line;
            }
        }
    }
    $gallery = array_values(array_unique(array_filter($gallery)));
    $galleryJson = $gallery ? json_encode($gallery) : null;
    if ($image === '' && $gallery) {
        $image = $gallery[0];
    }

    // Video: existing, remove, upload (auto-convert), or YouTube/Vimeo/file URL
    $video = (string) ($product['video'] ?? '');
    if (post('video_remove')) {
        $video = '';
    }
    $videoPath = trim((string) post('video_path'));
    if ($videoPath !== '') {
        $video = normalize_video_input($videoPath);
    }
    if (!empty($_FILES['video_file']) && ($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $vidUp = admin_process_video_upload($_FILES['video_file']);
        if ($vidUp['ok'] && $vidUp['path']) {
            $video = $vidUp['path'];
            if ($vidUp['converted']) {
                $notice = 'Video uploaded and converted to MP4 for browser playback.';
            } elseif (!empty($vidUp['error'])) {
                $notice = $vidUp['error'];
            } else {
                $notice = 'Video uploaded.';
            }
        } else {
            $error = $vidUp['error'] ?? 'Video upload failed.';
        }
    }
    $video = $video !== '' ? $video : null;

    if ($error) {
        // keep form state; video upload already set $error
    } elseif ($name === '' || $price < 0) {
        $error = 'Name and a valid price are required.';
    } else {
        if ($id > 0) {
            db()->prepare(
                'UPDATE products SET category_id=?, name=?, slug=?, short_description=?, description=?, base_price=?, compare_at_price=?, rating=?, review_count=?, image=?, gallery=?, video=?, is_featured=?, on_sale=?, is_new=?, is_active=?, seo_title=?, seo_description=?, focus_keyword=?, image_alt=?, faq_json=? WHERE id=?'
            )->execute([$categoryId, $name, $slug, $short, $desc, $price, $compareAt, $rating, $reviewCount, $image, $galleryJson, $video, $featured, $onSale, $isNew, $active, $seoTitle ?: null, $seoDescription ?: null, $focusKeyword ?: null, $imageAlt ?: null, $faqJson, $id]);
        } else {
            if ($image === '') {
                $image = 'assets/images/products/p1.svg';
            }
            db()->prepare(
                'INSERT INTO products (category_id, name, slug, short_description, description, base_price, compare_at_price, rating, review_count, image, gallery, video, is_featured, on_sale, is_new, is_active, seo_title, seo_description, focus_keyword, image_alt, faq_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$categoryId, $name, $slug, $short, $desc, $price, $compareAt, $rating, $reviewCount, $image, $galleryJson, $video, $featured, $onSale, $isNew, $active, $seoTitle ?: null, $seoDescription ?: null, $focusKeyword ?: null, $imageAlt ?: null, $faqJson]);
            $id = (int) db()->lastInsertId();
        }

        // Update existing variants (keyed by variant id)
        $vLabels = (array) post('variant_label', []);
        $vLengths = (array) post('variant_length', []);
        $vSkus = (array) post('variant_sku', []);
        $vPrices = (array) post('variant_price', []);
        $vStocks = (array) post('variant_stock', []);
        $vActive = (array) post('variant_active', []);
        $vDelete = (array) post('variant_delete', []);

        $updV = db()->prepare('UPDATE product_variants SET label=?, option_length=?, sku=?, price=?, stock=?, is_active=? WHERE id=? AND product_id=?');
        $delV = db()->prepare('DELETE FROM product_variants WHERE id=? AND product_id=?');
        foreach ($vPrices as $vid => $vp) {
            $vid = (int) $vid;
            if (isset($vDelete[$vid])) {
                $delV->execute([$vid, $id]);
                continue;
            }
            $updV->execute([
                trim((string) ($vLabels[$vid] ?? '')),
                (float) ($vLengths[$vid] ?? 0),
                trim((string) ($vSkus[$vid] ?? '')),
                (float) $vp,
                (int) ($vStocks[$vid] ?? 0),
                isset($vActive[$vid]) ? 1 : 0,
                $vid,
                $id,
            ]);
        }

        // Add new variants
        $nLabels = (array) post('new_label', []);
        $nLengths = (array) post('new_length', []);
        $nSkus = (array) post('new_sku', []);
        $nPrices = (array) post('new_price', []);
        $nStocks = (array) post('new_stock', []);
        $insV = db()->prepare('INSERT INTO product_variants (product_id, sku, label, option_length, price, stock, is_active) VALUES (?,?,?,?,?,?,1)');
        foreach ($nLabels as $i => $nl) {
            $nl = trim((string) $nl);
            $np = (float) ($nPrices[$i] ?? 0);
            if ($nl === '' && $np <= 0) {
                continue;
            }
            $insV->execute([
                $id,
                trim((string) ($nSkus[$i] ?? '')) ?: 'SKU-' . $id . '-' . random_int(100, 999),
                $nl ?: 'Option',
                (float) ($nLengths[$i] ?? 0),
                $np,
                (int) ($nStocks[$i] ?? 0),
            ]);
        }

        flash('success', 'Product saved.' . ($notice ? ' ' . $notice : ''));
        header('Location: product-edit.php?id=' . $id);
        exit;
    }
}

// Prepare gallery for display
$galleryList = [];
if ($product && !empty($product['gallery'])) {
    $decoded = json_decode((string) $product['gallery'], true);
    if (is_array($decoded)) {
        $galleryList = $decoded;
    }
}
$variantStock = array_sum(array_map(fn($v) => (int) $v['stock'], $variants));

require __DIR__ . '/_layout_top.php';
?>

<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
  <div>
    <a href="products.php" class="text-sm text-stone-500 hover:text-stone-900 flex items-center gap-1 mb-2"><?= admin_icon('arrow-left', 'w-4 h-4') ?> Back to products</a>
    <h1 class="font-display text-4xl"><?= $product ? 'Edit product' : 'Add product' ?></h1>
  </div>
  <?php if ($product): ?>
    <a href="<?= e(url('index.php?page=product&slug=' . urlencode($product['slug']))) ?>" target="_blank" class="flex items-center gap-2 rounded-full border border-stone-300 px-5 py-2.5 text-sm hover:bg-stone-100 transition"><?= admin_icon('external-link', 'w-4 h-4') ?> View on site</a>
  <?php endif; ?>
</div>

<?php if ($msg = flash('success')): ?><div class="mb-4 bg-emerald-50 text-emerald-700 rounded-xl px-4 py-3 text-sm flex items-center gap-2"><?= admin_icon('check-circle', 'w-4 h-4') ?><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 bg-rose-50 text-rose-700 rounded-xl px-4 py-3 text-sm"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice && $error): ?><div class="mb-4 bg-amber-50 text-amber-800 rounded-xl px-4 py-3 text-sm"><?= e($notice) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-[1fr_340px] gap-6 items-start">
  <?= csrf_field() ?>

  <div class="space-y-6">
    <!-- Details -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('file-text', 'w-4 h-4 text-stone-400') ?> Details</h2>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Product name</label>
        <input name="name" required value="<?= e($product['name'] ?? '') ?>" placeholder="Product name" class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-xs text-stone-500 mb-1 block">Slug</label>
          <input name="slug" value="<?= e($product['slug'] ?? '') ?>" placeholder="auto from name" class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
        </div>
        <div>
          <label class="text-xs text-stone-500 mb-1 block">Category</label>
          <select name="category_id" class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
            <option value="">No category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= isset($product['category_id']) && (int) $product['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Short description</label>
        <textarea name="short_description" rows="2" placeholder="One-line summary" class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($product['short_description'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Full description</label>
        <textarea name="description" rows="6" placeholder="Full description" class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($product['description'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Media -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('image', 'w-4 h-4 text-stone-400') ?> Media</h2>

      <div class="flex flex-wrap items-start gap-4">
        <div class="text-center">
          <p class="text-xs text-stone-500 mb-2">Featured image</p>
          <?php if (!empty($product['image']) && file_exists(ROOT_PATH . '/' . $product['image'])): ?>
            <img src="<?= e(asset($product['image'])) ?>" class="w-32 h-32 rounded-xl object-cover border border-stone-200" alt="">
          <?php else: ?>
            <div class="w-32 h-32 rounded-xl bg-stone-100 flex items-center justify-center text-stone-300"><?= admin_icon('image', 'w-8 h-8') ?></div>
          <?php endif; ?>
        </div>
        <div class="flex-1 min-w-[220px] space-y-3">
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Upload new featured image</label>
            <input type="file" name="image_file" accept="image/*" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-stone-900 file:text-white file:px-4 file:py-2 file:text-xs">
          </div>
          <div>
            <label class="text-xs text-stone-500 mb-1 block">…or set image path</label>
            <input name="image_path" value="<?= e($product['image'] ?? '') ?>" placeholder="assets/images/products/…" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </div>
        </div>
      </div>

      <div>
        <p class="text-xs text-stone-500 mb-2">Gallery (<?= count($galleryList) ?>)</p>
        <?php if ($galleryList): ?>
          <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-3">
            <?php foreach ($galleryList as $g): ?>
              <label class="relative block group cursor-pointer">
                <?php if (file_exists(ROOT_PATH . '/' . $g)): ?>
                  <img src="<?= e(asset($g)) ?>" class="w-full aspect-square rounded-lg object-cover border border-stone-200 peer-checked:opacity-40" alt="">
                <?php else: ?>
                  <span class="w-full aspect-square rounded-lg bg-stone-100 flex items-center justify-center text-[10px] text-stone-400 p-1 text-center break-all"><?= e(basename($g)) ?></span>
                <?php endif; ?>
                <span class="absolute top-1 right-1 bg-white/90 rounded-md px-1.5 py-0.5 text-[11px] flex items-center gap-1 shadow">
                  <input type="checkbox" name="gallery_remove[]" value="<?= e($g) ?>" class="accent-rose-500"> remove
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-xs text-stone-400 mb-3">No gallery images yet.</p>
        <?php endif; ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-stone-500 mb-1 block">Upload gallery images</label>
            <input type="file" name="gallery_files[]" accept="image/*" multiple class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-stone-900 file:text-white file:px-4 file:py-2 file:text-xs">
          </div>
          <div>
            <label class="text-xs text-stone-500 mb-1 block">…or add paths (one per line)</label>
            <textarea name="gallery_add" rows="2" placeholder="assets/images/products/…" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"></textarea>
          </div>
        </div>
      </div>

      <!-- Product video -->
      <div class="border-t border-stone-100 pt-4">
        <p class="text-xs text-stone-500 mb-2 flex items-center gap-1.5"><?= admin_icon('video', 'w-4 h-4 text-stone-400') ?> Product video</p>
        <?php
          $pVideo = (string) ($product['video'] ?? '');
          $pVideoMeta = parse_product_video($pVideo);
          $ffmpegOk = (bool) ffmpeg_binary();
        ?>
        <div class="flex flex-wrap items-start gap-4">
          <div class="shrink-0">
            <?php if ($pVideoMeta['type'] === 'youtube' || $pVideoMeta['type'] === 'vimeo'): ?>
              <div class="w-48 aspect-video rounded-xl overflow-hidden border border-stone-200 bg-black">
                <iframe src="<?= e((string) $pVideoMeta['embed']) ?>" class="w-full h-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
              </div>
              <label class="mt-2 flex items-center gap-1.5 text-xs text-rose-600"><input type="checkbox" name="video_remove" value="1" class="accent-rose-500"> Remove video</label>
            <?php elseif ($pVideoMeta['type'] === 'file'): ?>
              <video src="<?= e($pVideoMeta['src']) ?>" class="w-48 aspect-video rounded-xl object-cover border border-stone-200 bg-black" muted playsinline controls preload="metadata"></video>
              <label class="mt-2 flex items-center gap-1.5 text-xs text-rose-600"><input type="checkbox" name="video_remove" value="1" class="accent-rose-500"> Remove video</label>
            <?php else: ?>
              <div class="w-48 aspect-video rounded-xl bg-stone-100 flex items-center justify-center text-stone-300"><?= admin_icon('video', 'w-8 h-8') ?></div>
            <?php endif; ?>
          </div>
          <div class="flex-1 min-w-[240px] space-y-3">
            <div>
              <label class="text-xs text-stone-500 mb-1 block">Upload video (any common format)</label>
              <input type="file" name="video_file" accept="video/*,.mkv,.avi,.wmv,.flv,.mpeg,.mpg,.3gp,.m2ts,.mts" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-stone-900 file:text-white file:px-4 file:py-2 file:text-xs">
              <p class="text-[11px] text-stone-400 mt-1">
                <?= $ffmpegOk
                  ? 'Uploads are auto-converted to MP4 (H.264) for browser playback.'
                  : 'ffmpeg not found — install it on the server so uploads convert to MP4 automatically.' ?>
              </p>
            </div>
            <div>
              <label class="text-xs text-stone-500 mb-1 block">…or paste a YouTube / Vimeo / direct video URL</label>
              <input name="video_path" value="<?= e($pVideo) ?>" placeholder="https://youtube.com/watch?v=… or https://vimeo.com/… or MP4 URL" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
            </div>
            <p class="text-[11px] text-stone-400">Shown on the product gallery. Leave blank and upload a file, or paste a link. Upload wins if both are provided.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Variants -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-medium flex items-center gap-2"><?= admin_icon('layers', 'w-4 h-4 text-stone-400') ?> Variants <span class="text-xs text-stone-400 font-normal"><?= count($variants) ?> &middot; <?= $variantStock ?> in stock</span></h2>
        <button type="button" id="add-variant" class="flex items-center gap-1 rounded-full border border-stone-300 px-3 py-1.5 text-xs hover:bg-stone-100 transition"><?= admin_icon('plus', 'w-3.5 h-3.5') ?> Add variant</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
          <thead class="text-left text-stone-400 text-xs">
            <tr>
              <th class="py-2 pr-2">Label</th>
              <th class="py-2 px-2 w-20">Length</th>
              <th class="py-2 px-2 w-32">SKU</th>
              <th class="py-2 px-2 w-24">Price</th>
              <th class="py-2 px-2 w-20">Stock</th>
              <th class="py-2 px-2 w-16">Active</th>
              <th class="py-2 pl-2 w-16">Del</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($variants as $v): $vid = (int) $v['id']; ?>
              <tr class="border-t border-stone-100">
                <td class="py-2 pr-2"><input name="variant_label[<?= $vid ?>]" value="<?= e($v['label']) ?>" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
                <td class="py-2 px-2"><input type="number" step="0.5" name="variant_length[<?= $vid ?>]" value="<?= e((string) $v['option_length']) ?>" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
                <td class="py-2 px-2"><input name="variant_sku[<?= $vid ?>]" value="<?= e((string) $v['sku']) ?>" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
                <td class="py-2 px-2"><input type="number" step="0.01" name="variant_price[<?= $vid ?>]" value="<?= e((string) $v['price']) ?>" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
                <td class="py-2 px-2"><input type="number" name="variant_stock[<?= $vid ?>]" value="<?= (int) $v['stock'] ?>" class="w-full rounded-lg border <?= (int) $v['stock'] <= 5 ? 'border-amber-300 bg-amber-50' : 'border-stone-200' ?> px-2.5 py-1.5"></td>
                <td class="py-2 px-2 text-center"><input type="checkbox" name="variant_active[<?= $vid ?>]" value="1" <?= !isset($v['is_active']) || $v['is_active'] ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4"></td>
                <td class="py-2 pl-2 text-center"><input type="checkbox" name="variant_delete[<?= $vid ?>]" value="1" class="accent-rose-500 w-4 h-4"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tbody id="new-variants"></tbody>
        </table>
      </div>
      <?php if (!$variants): ?><p class="text-xs text-stone-400 mt-3">No variants yet — add one above, or a default set is created on first save.</p><?php endif; ?>
    </div>

    <?php
    $productFaqs = seo_parse_faq(isset($product['faq_json']) ? (string) $product['faq_json'] : null);
    if (!$productFaqs) {
        $productFaqs = [['question' => '', 'answer' => '']];
    }
    $serpTitle = trim((string) ($product['seo_title'] ?? '')) ?: trim((string) ($product['name'] ?? 'Product name'));
    $serpDesc = trim((string) ($product['seo_description'] ?? '')) ?: trim((string) ($product['short_description'] ?? 'Meta description preview…'));
    $serpUrl = !empty($product['slug']) ? url('product/' . $product['slug']) : url('product/your-slug');
    ?>
    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('search', 'w-4 h-4 text-stone-400') ?> SEO</h2>
      <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
        <p class="text-xs text-stone-400 mb-1">Google preview</p>
        <p class="text-[#1a0dab] text-lg leading-snug truncate" id="serp-title"><?= e(seo_format_title($serpTitle)) ?></p>
        <p class="text-[#006621] text-xs truncate" id="serp-url"><?= e($serpUrl) ?></p>
        <p class="text-sm text-stone-600 mt-1 line-clamp-2" id="serp-desc"><?= e(mb_substr($serpDesc, 0, 160)) ?></p>
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">SEO title <span class="text-stone-400" id="seo-title-count">0</span>/60</label>
        <input name="seo_title" id="seo-title" maxlength="70" value="<?= e($product['seo_title'] ?? '') ?>" placeholder="Defaults to product name" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Meta description <span class="text-stone-400" id="seo-desc-count">0</span>/160</label>
        <textarea name="seo_description" id="seo-description" rows="3" maxlength="320" placeholder="Defaults to short description" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]"><?= e($product['seo_description'] ?? '') ?></textarea>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-stone-500 mb-1 block">Focus keyword</label>
          <input name="focus_keyword" value="<?= e($product['focus_keyword'] ?? '') ?>" placeholder="e.g. afro kinky curly bundles" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
        </div>
        <div>
          <label class="text-xs text-stone-500 mb-1 block">Image alt text</label>
          <input name="image_alt" value="<?= e($product['image_alt'] ?? '') ?>" placeholder="Defaults to product name" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
        </div>
      </div>
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-stone-500 block">Product FAQs (schema)</label>
          <button type="button" id="add-faq" class="text-xs text-stone-600 underline">Add FAQ</button>
        </div>
        <div id="faq-list" class="space-y-3">
          <?php foreach ($productFaqs as $faq): ?>
            <div class="grid gap-2 rounded-xl border border-stone-100 p-3 bg-stone-50/50">
              <input name="faq_q[]" value="<?= e($faq['question']) ?>" placeholder="Question" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm">
              <textarea name="faq_a[]" rows="2" placeholder="Answer" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm"><?= e($faq['answer']) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="space-y-6 lg:sticky lg:top-6">
    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('settings', 'w-4 h-4 text-stone-400') ?> Status</h2>
      <label class="flex items-center justify-between text-sm"><span>Active</span><input type="checkbox" name="is_active" value="1" <?= !isset($product) || !empty($product['is_active']) ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4"></label>
      <label class="flex items-center justify-between text-sm"><span>Featured</span><input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?> class="accent-amber-500 w-4 h-4"></label>
      <label class="flex items-center justify-between text-sm"><span>New</span><input type="checkbox" name="is_new" value="1" <?= !empty($product['is_new']) ? 'checked' : '' ?> class="accent-stone-800 w-4 h-4"></label>
      <label class="flex items-center justify-between text-sm"><span>On sale</span><input type="checkbox" name="on_sale" value="1" <?= !empty($product['on_sale']) ? 'checked' : '' ?> class="accent-red-600 w-4 h-4"></label>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('tag', 'w-4 h-4 text-stone-400') ?> Pricing</h2>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Base price (GBP)</label>
        <input name="base_price" type="number" step="0.01" required value="<?= e((string) ($product['base_price'] ?? '')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
      <div>
        <label class="text-xs text-stone-500 mb-1 block">Compare-at price (was)</label>
        <input name="compare_at_price" type="number" step="0.01" value="<?= e((string) ($product['compare_at_price'] ?? '')) ?>" placeholder="Optional" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
      <h2 class="font-medium flex items-center gap-2"><?= admin_icon('star', 'w-4 h-4 text-stone-400') ?> Reviews</h2>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-stone-500 mb-1 block">Rating</label>
          <input name="rating" type="number" step="0.1" min="0" max="5" value="<?= e((string) ($product['rating'] ?? '5')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
        </div>
        <div>
          <label class="text-xs text-stone-500 mb-1 block"># Reviews</label>
          <input name="review_count" type="number" min="0" value="<?= e((string) ($product['review_count'] ?? '0')) ?>" class="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
        </div>
      </div>
    </div>

    <button class="w-full rounded-full bg-stone-900 text-white px-6 py-3.5 text-sm font-medium hover:bg-stone-800 transition flex items-center justify-center gap-2"><?= admin_icon('save', 'w-4 h-4') ?> Save product</button>
  </div>
</form>

<script>
  (function () {
    let n = 0;
    const tbody = document.getElementById('new-variants');
    document.getElementById('add-variant').addEventListener('click', function () {
      const tr = document.createElement('tr');
      tr.className = 'border-t border-stone-100 bg-emerald-50/40';
      tr.innerHTML = `
        <td class="py-2 pr-2"><input name="new_label[${n}]" placeholder="e.g. 18 inches" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
        <td class="py-2 px-2"><input type="number" step="0.5" name="new_length[${n}]" placeholder="18" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
        <td class="py-2 px-2"><input name="new_sku[${n}]" placeholder="auto" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
        <td class="py-2 px-2"><input type="number" step="0.01" name="new_price[${n}]" placeholder="0.00" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
        <td class="py-2 px-2"><input type="number" name="new_stock[${n}]" value="0" class="w-full rounded-lg border border-stone-200 px-2.5 py-1.5"></td>
        <td class="py-2 px-2 text-center text-xs text-emerald-600">new</td>
        <td class="py-2 pl-2 text-center"><button type="button" class="text-rose-500" onclick="this.closest('tr').remove()">&times;</button></td>`;
      tbody.appendChild(tr);
      n++;
    });

    const faqList = document.getElementById('faq-list');
    const addFaq = document.getElementById('add-faq');
    if (addFaq && faqList) {
      addFaq.addEventListener('click', function () {
        if (faqList.children.length >= 8) return;
        const wrap = document.createElement('div');
        wrap.className = 'grid gap-2 rounded-xl border border-stone-100 p-3 bg-stone-50/50';
        wrap.innerHTML = '<input name="faq_q[]" placeholder="Question" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm"><textarea name="faq_a[]" rows="2" placeholder="Answer" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm"></textarea>';
        faqList.appendChild(wrap);
      });
    }

    const titleInput = document.getElementById('seo-title');
    const descInput = document.getElementById('seo-description');
    const nameInput = document.querySelector('input[name="name"]');
    const shortInput = document.querySelector('textarea[name="short_description"]');
    const store = <?= json_encode(seo_store_name()) ?>;
    const pattern = <?= json_encode(seo_title_pattern()) ?>;
    const formatTitle = (page) => {
      page = (page || '').trim() || 'Product name';
      if (page.toLowerCase().includes(String(store).toLowerCase())) return page;
      return String(pattern).replace('{page}', page).replace('{store}', store);
    };
    const syncSerp = () => {
      const rawTitle = (titleInput && titleInput.value.trim()) || (nameInput && nameInput.value) || 'Product name';
      const rawDesc = (descInput && descInput.value.trim()) || (shortInput && shortInput.value) || 'Meta description preview…';
      const t = document.getElementById('serp-title');
      const d = document.getElementById('serp-desc');
      const tc = document.getElementById('seo-title-count');
      const dc = document.getElementById('seo-desc-count');
      if (t) t.textContent = formatTitle(rawTitle);
      if (d) d.textContent = rawDesc.slice(0, 160);
      if (tc && titleInput) tc.textContent = titleInput.value.length;
      if (dc && descInput) dc.textContent = descInput.value.length;
    };
    [titleInput, descInput, nameInput, shortInput].forEach((el) => {
      if (el) el.addEventListener('input', syncSerp);
    });
    syncSerp();
  })();
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
