<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$step    = 'upload';        // upload | preview | done
$errors  = [];
$preview = [];
$summary = ['create' => 0, 'update' => 0, 'skip' => 0];
$done    = null;
$tmpFile = $_SESSION['csv_import_tmp'] ?? null;
$tmpName = $_SESSION['csv_import_name'] ?? 'products.csv';

// ---------- POST: upload / confirm / cancel ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'cancel') {
        if ($tmpFile && is_file($tmpFile)) @unlink($tmpFile);
        unset($_SESSION['csv_import_tmp'], $_SESSION['csv_import_name']);
        flash('success', 'Import cancelled.');
        redirect('products-import.php');
    }

    if ($action === 'upload') {
        if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please choose a CSV file to upload.';
        } elseif ($_FILES['csv']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'CSV file is larger than 2 MB.';
        } else {
            $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv' && $ext !== 'txt') {
                $errors[] = 'File must be a .csv (or .txt) file.';
            } else {
                $saveDir = dirname(__DIR__) . '/uploads/imports';
                if (!is_dir($saveDir)) mkdir($saveDir, 0775, true);
                $target = $saveDir . '/import-' . time() . '-' . bin2hex(random_bytes(3)) . '.csv';
                if (move_uploaded_file($_FILES['csv']['tmp_name'], $target)) {
                    if ($tmpFile && is_file($tmpFile)) @unlink($tmpFile);
                    $_SESSION['csv_import_tmp']  = $target;
                    $_SESSION['csv_import_name'] = $_FILES['csv']['name'];
                    redirect('products-import.php?step=preview');
                } else {
                    $errors[] = 'Could not save the uploaded file.';
                }
            }
        }
    }

    if ($action === 'confirm') {
        if (!$tmpFile || !is_file($tmpFile)) {
            $errors[] = 'Upload session expired. Please upload the file again.';
        } else {
            try {
                $parsed = csv_read($tmpFile);

                $existingSkus = [];
                foreach (db()->query('SELECT id, sku FROM products')->fetchAll() as $r) {
                    $existingSkus[strtolower($r['sku'])] = (int)$r['id'];
                }
                $existingSlugs = [];
                foreach (db()->query('SELECT id, slug FROM products')->fetchAll() as $r) {
                    $existingSlugs[$r['slug']] = (int)$r['id'];
                }

                $pdo = db();
                $pdo->beginTransaction();

                $created = 0; $updated = 0; $skipped = 0; $skipErrors = [];
                $seenSkus = [];

                $insert = $pdo->prepare('INSERT INTO products (sku,name,slug,description,category,price,stock_qty,is_active) VALUES (?,?,?,?,?,?,?,?)');
                $update = $pdo->prepare('UPDATE products SET name=?, slug=?, description=?, category=?, price=?, stock_qty=?, is_active=? WHERE id=?');

                foreach ($parsed['rows'] as $i => $row) {
                    $lineNo = $i + 2;
                    $check = csv_validate_product_row($row, $existingSkus, $existingSlugs, $seenSkus, $lineNo);
                    if (!$check['ok']) {
                        $skipped++;
                        $skipErrors[] = "Line $lineNo: " . $check['error'];
                        continue;
                    }
                    $d = $check['data'];
                    if ($check['action'] === 'create') {
                        $insert->execute([$d['sku'], $d['name'], $d['slug'], $d['description'], $d['category'], $d['price'], $d['stock_qty'], $d['is_active']]);
                        $created++;
                        $existingSkus[strtolower($d['sku'])] = (int)$pdo->lastInsertId();
                        $existingSlugs[$d['slug']] = (int)$pdo->lastInsertId();
                    } else {
                        $update->execute([$d['name'], $d['slug'], $d['description'], $d['category'], $d['price'], $d['stock_qty'], $d['is_active'], $d['existing_id']]);
                        $updated++;
                    }
                }

                $pdo->commit();
                log_event('products.csv_imported', 'product', null, ['created' => $created, 'updated' => $updated, 'skipped' => $skipped]);

                @unlink($tmpFile);
                unset($_SESSION['csv_import_tmp'], $_SESSION['csv_import_name']);

                $done = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $skipErrors];
                $step = 'done';
            } catch (Throwable $ex) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log('CSV import failed: ' . $ex->getMessage());
                $errors[] = $ex->getMessage();
            }
        }
    }
}

// ---------- GET: preview if step=preview ----------
if ($step !== 'done' && isset($_GET['step']) && $_GET['step'] === 'preview') {
    if (!$tmpFile || !is_file($tmpFile)) {
        $errors[] = 'Upload session expired. Please upload again.';
    } else {
        try {
            $parsed = csv_read($tmpFile);
            $existingSkus = [];
            foreach (db()->query('SELECT id, sku FROM products')->fetchAll() as $r) {
                $existingSkus[strtolower($r['sku'])] = (int)$r['id'];
            }
            $existingSlugs = [];
            foreach (db()->query('SELECT id, slug FROM products')->fetchAll() as $r) {
                $existingSlugs[$r['slug']] = (int)$r['id'];
            }

            $seenSkus = [];
            foreach ($parsed['rows'] as $i => $row) {
                $lineNo = $i + 2;
                $check = csv_validate_product_row($row, $existingSkus, $existingSlugs, $seenSkus, $lineNo);
                if ($check['ok']) {
                    $preview[] = ['line' => $lineNo, 'status' => $check['action'], 'sku' => $check['data']['sku'], 'name' => $check['data']['name'], 'price' => $check['data']['price'], 'qty' => $check['data']['stock_qty'], 'error' => null];
                    $summary[$check['action']]++;
                } else {
                    $preview[] = ['line' => $lineNo, 'status' => 'skip', 'sku' => $row['sku'] ?? '', 'name' => $row['name'] ?? '', 'price' => '', 'qty' => '', 'error' => $check['error']];
                    $summary['skip']++;
                }
            }
            $step = 'preview';
        } catch (Throwable $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Import products | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
<main class="mx-auto max-w-6xl px-5 py-8">
  <a href="products.php" class="text-sm font-semibold text-brand-accent">← Back to products</a>
  <h1 class="mt-3 text-3xl font-black">Import products from CSV</h1>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 rounded-lg bg-rose-100 p-3 text-rose-800"><?= e($err) ?></p>
  <?php endforeach; ?>

  <?php if ($step === 'upload'): ?>
    <p class="mt-2 text-slate-500">Upload a CSV with columns: <code class="rounded bg-slate-200 px-1">sku, name, slug, category, price, stock_qty, description, is_active</code>. The SKU is the key — existing SKUs get updated, new ones get created.</p>
    <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload">
      <input type="file" name="csv" accept=".csv,text/csv" required class="w-full rounded-lg border border-slate-300 px-3 py-3">
      <div class="flex flex-wrap gap-3">
        <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Upload &amp; preview</button>
        <a href="products-import-template.php" class="rounded-lg border bg-white px-6 py-3 font-semibold">Download CSV template</a>
      </div>
    </form>

  <?php elseif ($step === 'preview'): ?>
    <p class="mt-2 text-slate-500">File: <strong><?= e($tmpName) ?></strong></p>
    <div class="mt-4 flex flex-wrap gap-3">
      <span class="rounded-full bg-emerald-100 px-4 py-2 text-sm font-bold text-emerald-800">New: <?= $summary['create'] ?></span>
      <span class="rounded-full bg-blue-100 px-4 py-2 text-sm font-bold text-blue-800">Update: <?= $summary['update'] ?></span>
      <span class="rounded-full bg-rose-100 px-4 py-2 text-sm font-bold text-rose-800">Skip: <?= $summary['skip'] ?></span>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
      <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
        <table class="w-full text-left text-sm">
          <thead class="sticky top-0 bg-slate-50 text-slate-500">
            <tr><th class="p-3">Line</th><th class="p-3">Action</th><th class="p-3">SKU</th><th class="p-3">Name</th><th class="p-3">Price</th><th class="p-3">Qty</th><th class="p-3">Note</th></tr>
          </thead>
          <tbody>
            <?php foreach ($preview as $p): ?>
              <tr class="border-t">
                <td class="p-3 text-slate-500"><?= (int)$p['line'] ?></td>
                <td class="p-3">
                  <?php if ($p['status'] === 'create'): ?><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">New</span>
                  <?php elseif ($p['status'] === 'update'): ?><span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-800">Update</span>
                  <?php else: ?><span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-bold text-rose-800">Skip</span><?php endif; ?>
                </td>
                <td class="p-3 font-mono text-xs"><?= e($p['sku']) ?></td>
                <td class="p-3"><?= e($p['name']) ?></td>
                <td class="p-3"><?= $p['price'] ? number_format((float)$p['price'], 2) : '—' ?></td>
                <td class="p-3"><?= $p['qty'] !== '' ? (int)$p['qty'] : '—' ?></td>
                <td class="p-3 text-xs text-rose-700"><?= e($p['error'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if (!$preview): ?>
      <p class="mt-6 rounded-xl bg-white p-6 text-center text-slate-500">CSV has no data rows.</p>
    <?php endif; ?>

    <div class="mt-6 flex flex-wrap gap-3">
      <?php if ($summary['create'] + $summary['update'] > 0): ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="confirm">
          <button onclick="return confirm('Import <?= $summary['create'] + $summary['update'] ?> product(s)? This cannot be undone.')" class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Import <?= $summary['create'] + $summary['update'] ?> row(s)</button>
        </form>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="cancel">
        <button class="rounded-lg border bg-white px-6 py-3 font-semibold">Cancel</button>
      </form>
    </div>

  <?php elseif ($step === 'done' && $done): ?>
    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
      <h2 class="text-xl font-black text-emerald-800">Import complete</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs text-emerald-700">Created</p><p class="text-3xl font-black text-emerald-800"><?= (int)$done['created'] ?></p></div>
        <div class="rounded-xl bg-blue-50 p-4"><p class="text-xs text-blue-700">Updated</p><p class="text-3xl font-black text-blue-800"><?= (int)$done['updated'] ?></p></div>
        <div class="rounded-xl bg-rose-50 p-4"><p class="text-xs text-rose-700">Skipped</p><p class="text-3xl font-black text-rose-800"><?= (int)$done['skipped'] ?></p></div>
      </div>
      <?php if ($done['errors']): ?>
        <h3 class="mt-6 font-bold text-rose-800">Skipped rows</h3>
        <ul class="mt-2 space-y-1 text-sm text-rose-700">
          <?php foreach ($done['errors'] as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="mt-6 flex gap-3">
        <a href="products.php" class="rounded-lg bg-brand-primary px-5 py-3 font-semibold text-brand-onPrimary">View products</a>
        <a href="products-import.php" class="rounded-lg border bg-white px-5 py-3 font-semibold">Import another file</a>
      </div>
    </div>
  <?php endif; ?>
</main>
</body></html>