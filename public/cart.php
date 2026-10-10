<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum(array_map('intval', $_SESSION['cart']))
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        flash('success', 'Cart cleared.');
        redirect('cart.php');
    }

    if ($action === 'remove') {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($id && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
            flash('success', 'Item removed.');
        }
        redirect('cart.php');
    }

    if ($action === 'update') {
        $qtys = $_POST['qty'] ?? [];
        if (is_array($qtys)) {
            $ids = array_map('intval', array_keys($qtys));
            if ($ids) {
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare("SELECT id, stock_qty FROM products WHERE id IN ($in)");
                $stmt->execute($ids);
                $stocks = [];
                foreach ($stmt->fetchAll() as $row) {
                    $stocks[(int)$row['id']] = (int)$row['stock_qty'];
                }
                foreach ($qtys as $pid => $q) {
                    $pid = (int)$pid;
                    $q   = (int)$q;
                    if (!isset($_SESSION['cart'][$pid])) continue;
                    if ($q <= 0) {
                        unset($_SESSION['cart'][$pid]);
                    } else {
                        $cap = min($stocks[$pid] ?? 99, 99);
                        $_SESSION['cart'][$pid] = max(1, min($q, $cap));
                    }
                }
            }
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0.0;

if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, sku, name, slug, price, stock_qty, image_path FROM products WHERE id IN ($in) AND is_active=1");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $qty = (int)($cart[$row['id']] ?? 0);
        if ($qty < 1) continue;
        $line = (float)$row['price'] * $qty;
        $subtotal += $line;
        $items[] = $row + ['qty' => $qty, 'line_total' => $line];
    }
}

$notice = flash('success');
$error  = flash('error');
$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your cart | <?= e($siteName) ?></title>
<?= render_brand_head() ?>
</head>
<body class="bg-slate-50 text-slate-800">

<header class="bg-brand-primary text-brand-onPrimary">
  <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <?= render_logo('public') ?>
    <div class="hidden gap-6 text-sm md:flex">
      <a href="index.php#services" class="hover:opacity-80">Services</a>
      <a href="shop.php" class="hover:opacity-80">Plumbing supplies</a>
      <a href="index.php#booking" class="hover:opacity-80">Book a service</a>
    </div>
    <div class="flex items-center gap-2">
      <a href="cart.php" class="rounded-lg border border-current/30 px-4 py-2 text-sm font-bold hover:bg-white/10">Cart (<?= (int)$cartCount ?>)</a>
      <a class="rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20have%20an%20enquiry.">WhatsApp us</a>
    </div>
  </nav>
</header>

<main class="mx-auto max-w-5xl px-5 py-10">
  <h1 class="text-3xl font-black">Your cart</h1>

  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="mt-4 rounded-lg bg-rose-100 p-3 text-rose-900"><?= e($error) ?></p><?php endif; ?>

  <?php if (!$items): ?>
    <div class="mt-6 rounded-2xl bg-white p-10 text-center shadow-sm">
      <p class="text-5xl">🛒</p>
      <p class="mt-4 text-slate-600">Your cart is empty.</p>
      <a href="shop.php" class="mt-6 inline-block rounded-lg bg-brand-primary px-5 py-3 font-semibold text-brand-onPrimary">Browse products</a>
    </div>
  <?php else: ?>
    <form method="post" class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-slate-500">
            <tr>
              <th class="p-4">Product</th>
              <th class="p-4">Price (KES)</th>
              <th class="p-4">Qty</th>
              <th class="p-4">Line total</th>
              <th class="p-4"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr class="border-t align-middle">
                <td class="p-4">
                  <div class="flex items-center gap-3">
                    <?php if (!empty($it['image_path'])): ?>
                      <img src="<?= e($it['image_path']) ?>" alt="" class="h-12 w-12 rounded object-cover">
                    <?php else: ?>
                      <div class="grid h-12 w-12 place-items-center rounded bg-slate-100 text-xl">🔧</div>
                    <?php endif; ?>
                    <div>
                      <a href="product.php?slug=<?= e(urlencode($it['slug'])) ?>" class="font-semibold hover:text-brand-accent"><?= e($it['name']) ?></a>
                      <p class="font-mono text-xs text-slate-500">SKU: <?= e($it['sku']) ?></p>
                      <p class="text-xs text-slate-500">Stock: <?= (int)$it['stock_qty'] ?></p>
                    </div>
                  </div>
                </td>
                <td class="p-4"><?= number_format((float)$it['price'], 2) ?></td>
                <td class="p-4">
                  <input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>"
                         min="0" max="<?= (int)$it['stock_qty'] ?>"
                         class="w-20 rounded-lg border border-slate-300 px-2 py-1">
                </td>
                <td class="p-4 font-bold"><?= number_format((float)$it['line_total'], 2) ?></td>
                <td class="p-4 text-right">
                  <button name="action" value="remove" formnovalidate
                          onclick="this.form.querySelector('input[name=product_id]').value='<?= (int)$it['id'] ?>'; return confirm('Remove this item?')"
                          class="rounded-md border px-3 py-1 text-xs font-semibold">Remove</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <input type="hidden" name="product_id" value="">
      <div class="flex flex-wrap items-center justify-between gap-3 border-t p-5">
        <button name="action" value="update" class="rounded-lg border bg-white px-5 py-3 font-semibold">Update quantities</button>
        <div class="text-right">
          <p class="text-sm text-slate-500">Subtotal</p>
          <p class="text-3xl font-black">KES <?= number_format($subtotal, 2) ?></p>
        </div>
      </div>
    </form>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="clear">
        <button onclick="return confirm('Clear the entire cart?')" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700">Clear cart</button>
      </form>
      <div class="flex gap-3">
        <a href="shop.php" class="rounded-lg border bg-white px-5 py-3 font-semibold">Continue shopping</a>
        <a href="checkout.php" class="rounded-lg bg-brand-accent px-6 py-3 font-bold text-brand-onAccent hover:bg-brand-accentHover">Proceed to checkout →</a>
      </div>
    </div>
  <?php endif; ?>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e($siteName) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>