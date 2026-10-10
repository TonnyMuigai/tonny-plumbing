<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') { header('Location: shop.php'); exit; }

$stmt = db()->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$p = $stmt->fetch();

if (!$p) {
    http_response_code(404);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Product not found</title><?= render_brand_head() ?></head>
    <body class="grid min-h-screen place-items-center bg-slate-50 p-5 text-center">
    <div class="rounded-2xl bg-white p-8 shadow">
      <h1 class="text-2xl font-black">Product not found</h1>
      <p class="mt-2 text-slate-600">This product may have been removed or is no longer available.</p>
      <a href="shop.php" class="mt-6 inline-block rounded-lg bg-brand-primary px-5 py-3 font-semibold text-brand-onPrimary">Back to shop</a>
    </div></body></html><?php
    exit;
}

$related = [];
if (!empty($p['category'])) {
    $rs = db()->prepare('SELECT id, sku, name, slug, price, stock_qty, image_path FROM products WHERE is_active = 1 AND category = ? AND id <> ? ORDER BY RAND() LIMIT 4');
    $rs->execute([$p['category'], $p['id']]);
    $related = $rs->fetchAll();
}

$inStock  = (int)$p['stock_qty'] > 5;
$lowStock = (int)$p['stock_qty'] > 0 && (int)$p['stock_qty'] <= 5;
$outStock = (int)$p['stock_qty'] === 0;

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum(array_map('intval', $_SESSION['cart'])) : 0;

$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
$orderMsg = rawurlencode("Hello $siteName, I'd like to order:\n{$p['name']} (SKU: {$p['sku']})\nPrice: KES " . number_format((float)$p['price'], 2) . "\nPlease confirm availability.");
$successMsg = flash('success');
$errorMsg   = flash('error');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($p['name']) ?> | <?= e($siteName) ?></title>
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
  <nav class="text-sm text-slate-500">
    <a href="index.php" class="hover:underline">Home</a>
    <span class="mx-1">/</span>
    <a href="shop.php" class="hover:underline">Shop</a>
    <?php if (!empty($p['category'])): ?><span class="mx-1">/</span><a href="shop.php?category=<?= urlencode($p['category']) ?>" class="hover:underline"><?= e($p['category']) ?></a><?php endif; ?>
    <span class="mx-1">/</span>
    <span class="text-slate-700"><?= e($p['name']) ?></span>
  </nav>

  <?php if ($successMsg): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($successMsg) ?></p><?php endif; ?>
  <?php if ($errorMsg): ?><p class="mt-4 rounded-lg bg-rose-100 p-3 text-rose-900"><?= e($errorMsg) ?></p><?php endif; ?>

  <div class="mt-6 grid gap-10 lg:grid-cols-2">
    <div>
      <?php if (!empty($p['image_path'])): ?>
        <img src="<?= e($p['image_path']) ?>" alt="<?= e($p['name']) ?>" class="w-full rounded-2xl border border-slate-200 object-cover">
      <?php else: ?>
        <div class="grid h-96 w-full place-items-center rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 text-7xl">🔧</div>
      <?php endif; ?>
    </div>

    <div>
      <?php if (!empty($p['category'])): ?>
        <a href="shop.php?category=<?= urlencode($p['category']) ?>" class="text-xs font-bold uppercase tracking-wide text-brand-accent hover:underline"><?= e($p['category']) ?></a>
      <?php endif; ?>
      <h1 class="mt-2 text-3xl font-black md:text-4xl"><?= e($p['name']) ?></h1>
      <p class="mt-2 font-mono text-sm text-slate-500">SKU: <?= e($p['sku']) ?></p>

      <div class="mt-6 flex flex-wrap items-center gap-4">
        <p class="text-4xl font-black">KES <?= number_format((float)$p['price'], 2) ?></p>
        <?php if ($inStock): ?>
          <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800">In stock (<?= (int)$p['stock_qty'] ?>)</span>
        <?php elseif ($lowStock): ?>
          <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">Low stock — only <?= (int)$p['stock_qty'] ?> left</span>
        <?php else: ?>
          <span class="rounded-full bg-rose-100 px-3 py-1 text-sm font-bold text-rose-800">Out of stock</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($p['description'])): ?>
        <div class="mt-6 whitespace-pre-line leading-7 text-slate-700"><?= e($p['description']) ?></div>
      <?php endif; ?>

      <div class="mt-8 flex flex-wrap gap-3">
        <?php if (!$outStock): ?>
          <form method="post" action="add-to-cart.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="back" value="product.php?slug=<?= e(urlencode($p['slug'])) ?>">
            <button class="rounded-xl bg-brand-accent px-6 py-4 font-bold text-brand-onAccent hover:bg-brand-accentHover">Add to cart</button>
          </form>
          <a target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $orderMsg ?>" class="rounded-xl bg-brand-cta px-6 py-4 font-bold text-brand-onCta hover:bg-brand-ctaHover">Order on WhatsApp</a>
        <?php else: ?>
          <a target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode("Hello $siteName, is \"{$p['name']}\" available again soon? (SKU: {$p['sku']})") ?>" class="rounded-xl border border-slate-300 bg-white px-6 py-4 font-bold hover:bg-slate-50">Ask when back in stock</a>
        <?php endif; ?>
        <a href="shop.php" class="rounded-xl border border-slate-300 bg-white px-6 py-4 font-bold hover:bg-slate-50">← Back to shop</a>
      </div>

      <div class="mt-8 rounded-xl bg-slate-100 p-4 text-sm text-slate-600">
        <p><strong>How ordering works:</strong> add items to your cart and check out, or click Order on WhatsApp for a quick single-item order. We confirm availability and delivery, then arrange payment.</p>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="mt-16">
      <h2 class="text-2xl font-black">More in <?= e($p['category']) ?></h2>
      <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($related as $r): ?>
          <a href="product.php?slug=<?= e(urlencode($r['slug'])) ?>" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <?php if (!empty($r['image_path'])): ?>
              <img src="<?= e($r['image_path']) ?>" alt="<?= e($r['name']) ?>" class="h-36 w-full object-cover">
            <?php else: ?>
              <div class="grid h-36 w-full place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-4xl">🔧</div>
            <?php endif; ?>
            <div class="p-4">
              <p class="font-bold leading-snug group-hover:text-brand-accent"><?= e($r['name']) ?></p>
              <p class="mt-1 text-lg font-black">KES <?= number_format((float)$r['price'], 2) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e($siteName) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>