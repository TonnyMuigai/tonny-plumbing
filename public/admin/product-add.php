<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$errors = [];
$old = [
    'sku' => '', 'name' => '', 'slug' => '', 'category' => '',
    'description' => '', 'price' => '', 'stock_qty' => '0',
    'image_path' => '',
];

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
        if (is_file($oldFile) && str_starts_with(realpath($oldFile) ?: '', realpath($uploadDir) ?: '')) @unlink($oldFile);
    }
    return 'uploads/products/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
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
            $old['image_path'] = handle_image_upload($_FILES['image'] ?? [], null) ?? '';
        } catch (RuntimeException $ex) {
            $errors['image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare('INSERT INTO products (sku,name,slug,description,category,price,stock_qty,image_path,is_active) VALUES (?,?,?,?,?,?,?,?,1)');
            $stmt->execute([
                $old['sku'], $old['name'], $old['slug'],
                $old['description'] !== '' ? $old['description'] : null,
                $old['category'] !== '' ? $old['category'] : null,
                number_format((float)$old['price'], 2, '.', ''),
                (int)$old['stock_qty'],
                $old['image_path'] !== '' ? $old['image_path'] : null,
            ]);
            $id = (int)db()->lastInsertId();
            log_event('product.created', 'product', $id, ['sku' => $old['sku'], 'name' => $old['name']]);
            flash('success', 'Product created successfully.');
            redirect('products.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors['_form'] = 'That SKU or slug already exists. Choose a different one.';
            } else {
                error_log('product-add failed: ' . $ex->getMessage());
                $errors['_form'] = 'Could not save the product. Please try again.';
            }
        }
    }
}

function old(string $k, array $old, string $default = ''): string {
    return htmlspecialchars($old[$k] ?? $default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add product | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
  <h1 class="mt-3 text-3xl font-black">Add a product</h1>

  <?php if (!empty($errors['_form'])): ?>
    <p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-800"><?= e($errors['_form']) ?></p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl bg-white p-6 shadow-sm md:p-8">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="grid gap-5 sm:grid-cols-2">
      <label class="block text-sm font-semibold">SKU *
        <input name="sku" maxlength="60" required value="<?= old('sku', $old) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['sku'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['sku']) ?></span><?php endif; ?>
      </label>
      <label class="block text-sm font-semibold">Category
        <input name="category" maxlength="100" value="<?= old('category', $old) ?>" placeholder="e.g. Fittings, Pipes, Taps" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['category'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['category']) ?></span><?php endif; ?>
      </label>
    </div>

    <label class="block text-sm font-semibold">Product name *
      <input name="name" maxlength="180" required value="<?= old('name', $old) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['name'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Slug *
      <input name="slug" maxlength="200" required value="<?= old('slug', $old) ?>" placeholder="e.g. pvc-elbow-half-inch" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-mono text-sm font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <span class="mt-1 block text-xs font-normal text-slate-500">Lowercase letters, numbers and hyphens only.</span>
      <?php if (!empty($errors['slug'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['slug']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Description
      <textarea name="description" rows="3" maxlength="2000" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20"><?= old('description', $old) ?></textarea>
    </label>

    <div class="grid gap-5 sm:grid-cols-2">
      <label class="block text-sm font-semibold">Price (KES) *
        <input name="price" inputmode="decimal" required value="<?= old('price', $old) ?>" placeholder="1250.00" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['price'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['price']) ?></span><?php endif; ?>
      </label>
      <label class="block text-sm font-semibold">Stock quantity *
        <input name="stock_qty" inputmode="numeric" required value="<?= old('stock_qty', $old, '0') ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['stock_qty'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['stock_qty']) ?></span><?php endif; ?>
      </label>
    </div>

    <label class="block text-sm font-semibold">Product image
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal">
      <span class="mt-1 block text-xs font-normal text-slate-500">JPG, PNG or WEBP. Max 2 MB.</span>
      <?php if (!empty($errors['image'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['image']) ?></span><?php endif; ?>
    </label>

    <div class="flex gap-3">
      <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Save product</button>
      <a href="products.php" class="rounded-lg border bg-white px-6 py-3 font-semibold">Cancel</a>
    </div>
  </form>
</main>
</body></html>