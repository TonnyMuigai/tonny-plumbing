<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum(array_map('intval', $_SESSION['cart']))
    : 0;

$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    flash('error', 'Your cart is empty.');
    redirect('shop.php');
}

$ids = array_map('intval', array_keys($cart));
$in  = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare("SELECT id, sku, name, price, stock_qty FROM products WHERE id IN ($in) AND is_active=1");
$stmt->execute($ids);
$items = [];
$subtotal = 0.0;
foreach ($stmt->fetchAll() as $row) {
    $qty = (int)($cart[$row['id']] ?? 0);
    if ($qty < 1) continue;
    if ($qty > (int)$row['stock_qty']) $qty = (int)$row['stock_qty'];
    if ($qty < 1) continue;
    $line = (float)$row['price'] * $qty;
    $subtotal += $line;
    $items[] = $row + ['qty' => $qty, 'line_total' => $line];
}

if (!$items) {
    $_SESSION['cart'] = [];
    flash('error', 'Your cart contains only unavailable products.');
    redirect('shop.php');
}

$errors = [];
$old = ['name' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']    = trim((string)($_POST['name'] ?? ''));
    $old['phone']   = trim((string)($_POST['phone'] ?? ''));
    $old['address'] = trim((string)($_POST['address'] ?? ''));

    if ($old['name'] === '' || mb_strlen($old['name']) > 150) {
        $errors['name'] = 'Enter your full name.';
    }
    if ($old['phone'] === '' || mb_strlen($old['phone']) > 30 || strlen(preg_replace('/\D+/', '', $old['phone'])) < 9) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors['address'] = 'Enter a delivery address or pickup point.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $subtotal = 0.0;
            $finalItems = [];
            foreach ($items as $it) {
                $s = $pdo->prepare('SELECT price, stock_qty FROM products WHERE id=? AND is_active=1 FOR UPDATE');
                $s->execute([$it['id']]);
                $row = $s->fetch();
                if (!$row || (int)$row['stock_qty'] < $it['qty']) {
                    throw new RuntimeException($it['name'] . ' is no longer available in the quantity requested.');
                }
                $line = (float)$row['price'] * $it['qty'];
                $subtotal += $line;
                $finalItems[] = [
                    'id'         => $it['id'],
                    'name'       => $it['name'],
                    'sku'        => $it['sku'],
                    'qty'        => $it['qty'],
                    'unit_price' => (float)$row['price'],
                    'line_total' => $line,
                ];
            }

            $pdo->prepare('INSERT INTO customers (name,phone,email) VALUES (?,?,NULL) ON DUPLICATE KEY UPDATE name=VALUES(name)')
                ->execute([$old['name'], $old['phone']]);
            $s = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
            $s->execute([$old['phone']]);
            $customerId = (int)$s->fetchColumn();

            $orderRef = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $ins = $pdo->prepare('INSERT INTO shop_orders (order_ref,customer_id,customer_name,customer_phone,delivery_address,status,subtotal,total) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$orderRef, $customerId, $old['name'], $old['phone'], $old['address'], 'pending', number_format($subtotal,2,'.',''), number_format($subtotal,2,'.','')]);
            $orderId = (int)$pdo->lastInsertId();

            $itemIns = $pdo->prepare('INSERT INTO shop_order_items (order_id,product_id,product_name,sku,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?,?)');
            foreach ($finalItems as $fi) {
                $itemIns->execute([$orderId, $fi['id'], $fi['name'], $fi['sku'], $fi['qty'],
                    number_format($fi['unit_price'],2,'.',''), number_format($fi['line_total'],2,'.','')]);
            }

            $dec = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id=? AND stock_qty >= ?');
            foreach ($finalItems as $fi) {
                $dec->execute([$fi['qty'], $fi['id'], $fi['qty']]);
            }

            $siteName = setting('site_name', 'TONNY');
            $msg = "New shop order {$orderRef}\nName: {$old['name']}\nPhone: {$old['phone']}\nAddress: {$old['address']}\n\n";
            foreach ($finalItems as $fi) {
                $msg .= "- {$fi['name']} (x{$fi['qty']}) = KES " . number_format($fi['line_total'], 2) . "\n";
            }
            $msg .= "\nTotal: KES " . number_format($subtotal, 2);
            $pdo->prepare('INSERT INTO notification_logs (channel,recipient,subject,message,status,related_type,related_id) VALUES (?,?,?,?,?,?,?)')
                ->execute(['whatsapp', setting('whatsapp_number','254723777601'), 'New order '.$orderRef, $msg, 'queued', 'shop_order', $orderId]);

            $pdo->commit();

            $_SESSION['cart'] = [];
            $_SESSION['last_order_ref'] = $orderRef;
            $_SESSION['last_order_total'] = $subtotal;
            $_SESSION['last_order_message'] = $msg;
            redirect('order-success.php');
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('checkout failed: ' . $ex->getMessage());
            $errors['_form'] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not place your order right now. Please try again.';
        }
    }
}

$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout | <?= e($siteName) ?></title>
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
  <h1 class="text-3xl font-black">Checkout</h1>
  <p class="mt-2 text-slate-600">Enter your delivery details. We'll confirm your order on WhatsApp and arrange payment.</p>

  <?php if (!empty($errors['_form'])): ?>
    <p class="mt-4 rounded-lg bg-rose-100 p-3 text-rose-800"><?= e($errors['_form']) ?></p>
  <?php endif; ?>

  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
    <form method="post" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm md:p-8">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label class="block text-sm font-semibold">Full name *
        <input name="name" maxlength="150" required value="<?= e($old['name']) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['name'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['name']) ?></span><?php endif; ?>
      </label>

      <label class="block text-sm font-semibold">Phone number *
        <input name="phone" maxlength="30" required value="<?= e($old['phone']) ?>" placeholder="07xx xxx xxx" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['phone'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['phone']) ?></span><?php endif; ?>
      </label>

      <label class="block text-sm font-semibold">Delivery address or pickup point *
        <input name="address" maxlength="255" required value="<?= e($old['address']) ?>" placeholder="Estate, road, town, landmark" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['address'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['address']) ?></span><?php endif; ?>
      </label>

      <button class="w-full rounded-xl bg-brand-accent px-5 py-4 font-bold text-brand-onAccent hover:bg-brand-accentHover">Place order</button>
      <p class="text-center text-xs text-slate-500">No payment is taken on this site yet. We confirm on WhatsApp first.</p>
    </form>

    <aside class="h-fit rounded-2xl bg-white p-6 shadow-sm">
      <h2 class="font-bold">Order summary</h2>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach ($items as $it): ?>
          <li class="flex justify-between gap-3">
            <span><?= e($it['name']) ?> × <?= (int)$it['qty'] ?></span>
            <span class="font-semibold">KES <?= number_format((float)$it['line_total'], 2) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="mt-4 flex justify-between border-t pt-4 text-lg font-black">
        <span>Total</span>
        <span>KES <?= number_format($subtotal, 2) ?></span>
      </div>
    </aside>
  </div>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e($siteName) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>