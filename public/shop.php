<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$q        = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 24;
$offset   = ($page - 1) * $perPage;

$where  = ['is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE ? OR sku LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM products $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = db()->prepare("SELECT id, sku, name, slug, category, price, stock_qty, image_path FROM products $whereSql ORDER BY name ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = db()->query("SELECT DISTINCT category FROM products WHERE is_active = 1 AND category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum(array_map('intval', $_SESSION['cart'])) : 0;

function qs(array $overrides = []): string {
    $base = ['q' => $_GET['q'] ?? '', 'category' => $_GET['category'] ?? '', 'page' => $_GET['page'] ?? 1];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return $merged ? '?' . http_build_query($merged) : '';
}

$whatsapp = setting('whatsapp_number', '254723777601');
$successMsg = flash('success');
$errorMsg   = flash('error');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Plumbing supplies | <?= e(setting('site_name','TONNY')) ?></title>
<?= render_brand_head() ?>
</head>
<body class="bg-slate-50 text-slate-800">

<header class="bg-brand-primary text-brand-onPrimary">
  <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <?= render_logo('public') ?>
    <div class="hidden gap-6 text-sm md:flex">
      <a href="index.php#services" class="hover:opacity-80">Services</a>
      <a href="shop.php" class="font-bold underline underline-offset-4">Plumbing supplies</a>
      <a href="index.php#booking" class="hover:opacity-80">Book a service</a>
    </div>
    <div class="flex items-center gap-2">
      <a href="cart.php" class="rounded-lg border border-current/30 px-4 py-2 text-sm font-bold hover:bg-white/10">Cart (<?= (int)$cartCount ?>)</a>
      <a class="rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20have%20an%20enquiry.">WhatsApp us</a>
    </div>
  </nav>
</header>

<main class="mx-auto max-w-7xl px-5 py-10">
  <div class="max-w-2xl">
    <p class="font-bold uppercase tracking-widest text-brand-accent">Plumbing shop</p>
    <h1 class="mt-2 text-3xl font-black md:text-4xl">Quality components for your project</h1>
    <p class="mt-3 text-slate-600">Browse our stock. Prices in KES. Add to cart or order instantly via WhatsApp.</p>
  </div>

  <?php if ($successMsg): ?><p class="mt-5 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($successMsg) ?></p><?php endif; ?>
  <?php if ($errorMsg): ?><p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-900"><?= e($errorMsg) ?></p><?php endif; ?>

  <form method="get" class="mt-7 grid gap-3 md:grid-cols-[1fr_240px_auto]">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, SKU or description…" class="rounded-lg border border-slate-300 bg-white px-4 py-3">
    <select name="category" class="rounded-lg border border-slate-300 bg-white px-4 py-3">
      <option value="">All categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="flex gap-2">
      <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Filter</button>
      <?php if ($q !== '' || $category !== ''): ?><a href="shop.php" class="rounded-lg border bg-white px-5 py-3 font-semibold">Clear</a><?php endif; ?>
    </div>
  </form>

  <p class="mt-5 text-sm text-slate-500">
    <?php if ($total === 0): ?>
      No products found<?= $q !== '' ? ' for "' . e($q) . '"' : '' ?><?= $category !== '' ? ' in ' . e($category) : '' ?>.
    <?php else: ?>
      Showing <strong><?= count($products) ?></strong> of <strong><?= $total ?></strong> product<?= $total === 1 ? '' : 's' ?>
      <?php if ($category !== ''): ?> in <strong><?= e($category) ?></strong><?php endif; ?>
      <?php if ($q !== ''): ?> matching "<strong><?= e($q) ?></strong>"<?php endif; ?>.
    <?php endif; ?>
  </p>

  <?php if ($products): ?>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      <?php foreach ($products as $p):
        $inStock  = (int)$p['stock_qty'] > 5;
        $lowStock = (int)$p['stock_qty'] > 0 && (int)$p['stock_qty'] <= 5;
        $outStock = (int)$p['stock_qty'] === 0;
      ?>
        <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
          <a href="product.php?slug=<?= e(urlencode($p['slug'])) ?>" class="block">
            <?php if (!empty($p['image_path'])): ?>
              <img src="<?= e($p['image_path']) ?>" alt="<?= e($p['name']) ?>" class="h-44 w-full object-cover">
            <?php else: ?>
              <div class="grid h-44 w-full place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-4xl">🔧</div>
            <?php endif; ?>
          </a>
          <div class="flex flex-1 flex-col p-4">
            <?php if (!empty($p['category'])): ?>
              <p class="text-xs font-bold uppercase tracking-wide text-brand-accent"><?= e($p['category']) ?></p>
            <?php endif; ?>
            <a href="product.php?slug=<?= e(urlencode($p['slug'])) ?>" class="mt-1 font-bold leading-snug hover:text-brand-accent"><?= e($p['name']) ?></a>
            <p class="mt-1 font-mono text-xs text-slate-500">SKU: <?= e($p['sku']) ?></p>

            <div class="mt-3 flex items-center justify-between">
              <p class="text-xl font-black">KES <?= number_format((float)$p['price'], 2) ?></p>
              <?php if ($inStock): ?>
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">In stock</span>
              <?php elseif ($lowStock): ?>
                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Low stock</span>
              <?php else: ?>
                <span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-bold text-rose-800">Out of stock</span>
              <?php endif; ?>
            </div>

            <div class="mt-4 flex gap-2">
              <a href="product.php?slug=<?= e(urlencode($p['slug'])) ?>" class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-sm font-semibold hover:bg-slate-50">Details</a>
              <?php if (!$outStock): ?>
                <form method="post" action="add-to-cart.php" class="flex-1">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <input type="hidden" name="back" value="<?= e(qs()) ?>">
                  <button class="w-full rounded-lg bg-brand-accent px-3 py-2 text-sm font-bold text-brand-onAccent hover:bg-brand-accentHover">Add to cart</button>
                </form>
              <?php else: ?>
                <span class="flex-1 rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-semibold text-slate-400">Unavailable</span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="mt-10 flex flex-wrap items-center justify-center gap-2">
        <?php if ($page > 1): ?><a href="<?= e(qs(['page' => $page - 1])) ?>" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50">← Previous</a><?php endif; ?>
        <span class="px-4 py-2 text-sm text-slate-600">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= e(qs(['page' => $page + 1])) ?>" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <section class="mt-16 rounded-2xl bg-brand-primary p-8 text-brand-onPrimary md:p-10">
    <div class="grid items-center gap-6 md:grid-cols-[1fr_auto]">
      <div>
        <h2 class="text-2xl font-black">Can't find what you need?</h2>
        <p class="mt-2 opacity-80">Tell us the part or fitting you're looking for and we'll source it for you.</p>
      </div>
      <a target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%27m%20looking%20for%20a%20specific%20plumbing%20part." class="rounded-xl bg-brand-cta px-6 py-3 font-bold text-brand-onCta hover:bg-brand-ctaHover">Request a part</a>
    </div>
  </section>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e(setting('site_name','TONNY')) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e(setting('site_name','TONNY')) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>