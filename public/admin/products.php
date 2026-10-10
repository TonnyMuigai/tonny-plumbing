<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');
    if (!$id || !in_array($action, ['activate', 'deactivate'], true)) {
        flash('success', 'Invalid action.');
        redirect('products.php');
    }
    $newState = $action === 'activate' ? 1 : 0;
    $stmt = db()->prepare('UPDATE products SET is_active=? WHERE id=?');
    $stmt->execute([$newState, $id]);
    log_event('product.' . $action, 'product', $id);
    flash('success', $action === 'activate' ? 'Product activated.' : 'Product deactivated.');
    redirect('products.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$sql = 'SELECT id, sku, name, slug, category, price, stock_qty, is_active, created_at FROM products';
if ($q !== '') {
    $sql .= ' WHERE name LIKE ? OR sku LIKE ? OR category LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$notice = flash('success');
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Products | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
<main class="mx-auto max-w-7xl px-5 py-8">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-3xl font-black">Plumbing supplies</h1>
      <p class="mt-2 text-slate-500">Manage products, prices (KES) and stock levels.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="products-import-template.php" class="rounded-lg border bg-white px-4 py-3 font-semibold">Download template</a>
      <a href="products-import.php" class="rounded-lg border bg-white px-4 py-3 font-semibold">Import CSV</a>
      <a href="product-add.php" class="rounded-lg bg-brand-primary px-5 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">+ Add product</a>
    </div>
  </div>

  <?php if ($notice): ?><p class="mt-5 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>

  <form method="get" class="mt-6 flex flex-wrap gap-3">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, SKU or category" class="flex-1 min-w-[240px] rounded-lg border border-slate-300 bg-white px-4 py-3">
    <button class="rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white">Search</button>
    <?php if ($q !== ''): ?><a href="products.php" class="rounded-lg border bg-white px-5 py-3 font-semibold">Clear</a><?php endif; ?>
  </form>

  <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr>
            <th class="p-4">SKU</th>
            <th class="p-4">Name</th>
            <th class="p-4">Category</th>
            <th class="p-4">Price (KES)</th>
            <th class="p-4">Stock</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <tr class="border-t align-top">
              <td class="p-4 font-mono text-xs"><?= e($p['sku']) ?></td>
              <td class="p-4">
                <p class="font-semibold"><?= e($p['name']) ?></p>
                <p class="text-xs text-slate-500">slug: <?= e($p['slug']) ?></p>
              </td>
              <td class="p-4"><?= e($p['category'] ?? '—') ?></td>
              <td class="p-4 font-semibold"><?= number_format((float)$p['price'], 2) ?></td>
              <td class="p-4"><span class="<?= $p['stock_qty'] <= 5 ? 'text-rose-700 font-bold' : '' ?>"><?= (int)$p['stock_qty'] ?></span></td>
              <td class="p-4">
                <?php if ((int)$p['is_active'] === 1): ?>
                  <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Active</span>
                <?php else: ?>
                  <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="p-4 text-right">
                <a href="product-edit.php?id=<?= (int)$p['id'] ?>" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Edit</a>
                <form method="post" class="inline">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="action" value="<?= (int)$p['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                  <button class="ml-1 rounded-md border px-3 py-2 text-xs font-semibold" onclick="return confirm('<?= (int)$p['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this product?')"><?= (int)$p['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="p-8 text-center text-slate-500">No products found<?= $q !== '' ? ' for "' . e($q) . '"' : '' ?>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body></html>