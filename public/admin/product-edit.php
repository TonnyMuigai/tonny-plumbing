<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { flash('success', 'Product not found.'); redirect('products.php'); }

$stmt = db()->prepare('SELECT * FROM products WHERE id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { flash('success', 'Product not found.'); redirect('products.php'); }

function handle_image_upload(array $file, ?string $existingPath = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return $existingPath;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed. Please try again.');
    if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Image is larger than 2 MB.');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    $ext = $allowed[$mime];
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($file['name'], PATHINFO_FILENAME)));
    $base = trim($base, '-') ?: 'product';
    $uploadDir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $filename = $base . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) throw new RuntimeException('Could not save the uploaded image.');
    if ($existingPath !== null && $existingPath !== '') {
        $oldFile = dirname(__DIR__) . '/' . ltrim($existingPath, '/');
        $ru = realpath($uploadDir) ?: ''; $ro = realpath($oldFile) ?: '';
        if ($ro !== '' && $ru !== '' && str_starts_with($ro, $ru)) @unlink($ro);
    }
    return 'uploads/products/' . $filename;
}

$errors = [];
$old = [
    'sku' => $product['sku'], 'name' => $product['name'], 'slug' => $product['slug'],
    'category' => $product['category'] ?? '', 'description' => $product['description'] ?? '',
    'price' => $product['price'], 'stock_qty' => $product['stock_qty'],
    'image_path' => $product['image_path'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        if (!empty($product['image_path'])) {
            $img = dirname(__DIR__) . '/' . ltrim($product['image_path'], '/');
            if (is_file($img)) @unlink($img);
        }
        db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        log_event('product.deleted', 'product', $id, ['sku' => $product['sku'], 'name' => $product['name']]);
        flash('success', 'Product deleted.');
        redirect('products.php');
    }

    if ($action === 'remove_image') {
        if (!empty($product['image_path'])) {
            $img = dirname(__DIR__) . '/' . ltrim($product['image_path'], '/');
            if (is_file($img)) @unlink($img);
        }
        db()->prepare('UPDATE products SET image_path=NULL WHERE id=?')->execute([$id]);
        log_event('product.image_removed', 'product', $id);
        flash('success', 'Image removed.');
        redirect('product-edit.php?id=' . $id);
    }

    $old['sku']         = trim((string)($_POST['sku'] ?? ''));
    $old['name']        = trim((string)($_POST['name'] ?? ''));
    $old['slug']        = strtolower(trim((string)($_POST['slug'] ?? '')));
    $old['category']    = trim((string)($_POST['category'] ?? ''));
    $old['description'] = trim((string)($_POST['description'] ?? ''));
    $old['price']       = trim((string)($_POST['price'] ?? ''));
    $old['stock_qty']   = trim((string)($_POST['stock_qty'] ?? '0'));

    if ($old['sku'] === '' || strlen($old['sku']) > 60) $errors['sku'] = 'SKU is required (max 60 characters).';
    if ($old['name'] === '' || mb_strlen($old['name']) > 180) $errors['name'] = 'Name is required (max 180 characters).';
    if ($old['slug'] === '' || strlen($old['slug']) > 200) {
        $errors['slug'] = 'Slug is required (max 200 characters).';
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $old['slug'])) {
        $errors['slug'] = 'Slug may only contain lowercase letters, numbers and hyphens.';
    }
    if ($old['price'] === '' || !is_numeric($old['price']) || (float)$old['price'] < 0) $errors['price'] = 'Enter a valid non-negative price.';
    if ($old['stock_qty'] === '' || !ctype_digit($old['stock_qty'])) $errors['stock_qty'] = 'Stock must be a whole number (0 or more).';
    if (mb_strlen($old['category']) > 100) $errors['category'] = 'Category is too long.';

    if (!$errors) {
        try {
            $newImagePath = handle_image_upload($_FILES['image'] ?? [], $product['image_path'] ?: null);
            $old['image_path'] = $newImagePath ?? '';
        } catch (RuntimeException $ex) {
            $errors['image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare('UPDATE products SET sku=?, name=?, slug=?, description=?, category=?, price=?, stock_qty=?, image_path=? WHERE id=?');
            $stmt->execute([
                $old['sku'], $old['name'], $old['slug'],
                $old['description'] !== '' ? $old['description'] : null,
                $old['category'] !== '' ? $old['category'] : null,
                number_format((float)$old['price'], 2, '.', ''),
                (int)$old['stock_qty'],
                $old['image_path'] !== '' ? $old['image_path'] : null,
                $id,
            ]);
            log_event('product.updated', 'product', $id, ['sku' => $old['sku'], 'name' => $old['name']]);
            flash('success', 'Product updated.');
            redirect('products.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors['_form'] = 'That SKU or slug is already used by another product.';
            } else {
                error_log('product-edit failed: ' . $ex->getMessage());
                $errors['_form'] = 'Could not save changes. Please try again.';
            }
        }
    }
    $product = array_merge($product, $old);
}

function old(string $k, array $old, string $default = ''): string {
    return htmlspecialchars((string)($old[$k] ?? $default), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit product | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
<?= render_brand_head() ?>
</head><body class="min-h-screen bg-slate-100 text-slate-800">
<header class="bg-slate-900 text-white">
  <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <a href="index.php" class="text-xl font-black"><?= e(setting('site_name','TONNY')) ?> <span class="font-normal text-slate-300">Admin</span></a>
    <nav class="flex flex-wrap items-center gap-5 text-sm">
      <?php $current = basename($_SERVER['PHP_SELF']); foreach (nav_items() as $file => [$label, $visible]): if (!$visible) continue; ?>
        <a href="<?= e($file) ?>" class="<?= $current === $file ? 'font-bold text-brand-accent' : 'hover:underline' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="profile.php" class="<?= $current === 'profile.php' ? 'font-bold text-brand-accent' : 'hover:underline' ?>">My profile</a>
      <form method="post" action="logout.php" class="inline">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="underline">Log out</button>
      </form>
    </nav>
  </div>
</header>
<main class="mx-auto max-w-3xl px-5 py-8">
  <a href="products.php" class="text-sm font-semibold text-brand-accent">← Back to products</a>
  <div class="mt-3 flex items-center justify-between">
    <h1 class="text-3xl font-black">Edit product</h1>
    <?php if ((int)$product['is_active'] === 1): ?>
      <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Active</span>
    <?php else: ?>
      <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700">Inactive</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($errors['_form'])): ?>
    <p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-800"><?= e($errors['_form']) ?></p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl bg-white p-6 shadow-sm md:p-8">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="action" value="save">

    <div class="grid gap-5 sm:grid-cols-2">
      <label class="block text-sm font-semibold">SKU *
        <input name="sku" maxlength="60" required value="<?= old('sku', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['sku'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['sku']) ?></span><?php endif; ?>
      </label>
      <label class="block text-sm font-semibold">Category
        <input name="category" maxlength="100" value="<?= old('category', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['category'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['category']) ?></span><?php endif; ?>
      </label>
    </div>

    <label class="block text-sm font-semibold">Product name *
      <input name="name" maxlength="180" required value="<?= old('name', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['name'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Slug *
      <input name="slug" maxlength="200" required value="<?= old('slug', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-mono text-sm font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['slug'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['slug']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Description
      <textarea name="description" rows="3" maxlength="2000" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20"><?= old('description', $product) ?></textarea>
    </label>

    <div class="grid gap-5 sm:grid-cols-2">
      <label class="block text-sm font-semibold">Price (KES) *
        <input name="price" inputmode="decimal" required value="<?= old('price', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['price'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['price']) ?></span><?php endif; ?>
      </label>
      <label class="block text-sm font-semibold">Stock quantity *
        <input name="stock_qty" inputmode="numeric" required value="<?= old('stock_qty', $product) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['stock_qty'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['stock_qty']) ?></span><?php endif; ?>
      </label>
    </div>

    <div class="rounded-xl border border-slate-200 p-4">
      <p class="text-sm font-semibold">Current image</p>
      <?php if (!empty($product['image_path'])): ?>
        <div class="mt-3 flex items-start gap-4">
          <img src="../<?= e($product['image_path']) ?>" alt="Current product image" class="h-32 w-32 rounded-lg object-cover border border-slate-200">
          <div class="text-sm"><p class="text-slate-600 break-all"><?= e($product['image_path']) ?></p></div>
        </div>
      <?php else: ?>
        <p class="mt-3 text-sm text-slate-500">No image uploaded yet.</p>
      <?php endif; ?>

      <label class="mt-4 block text-sm font-semibold">Replace image
        <input type="file" name