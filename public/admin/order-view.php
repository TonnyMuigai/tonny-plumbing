<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash('success', 'Order not found.');
    redirect('orders.php');
}

$stmt = db()->prepare('SELECT * FROM shop_orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    flash('success', 'Order not found.');
    redirect('orders.php');
}

$itemStmt = db()->prepare('SELECT product_name, sku, quantity, unit_price, line_total FROM shop_order_items WHERE order_id = ? ORDER BY id ASC');
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$whatsapp = setting('whatsapp_number', '254723777601');
$msg = "Order {$order['order_ref']}\n";
$msg .= "Name: {$order['customer_name']}\n";
$msg .= "Phone: {$order['customer_phone']}\n";
$msg .= "Address: " . ($order['delivery_address'] ?: '—') . "\n\n";
foreach ($items as $it) {
    $msg .= "- {$it['product_name']} (x{$it['quantity']}) = KES " . number_format((float)$it['line_total'], 2) . "\n";
}
$msg .= "\nTotal: KES " . number_format((float)$order['total'], 2);
$wa = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($msg);

$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-800',
    'confirmed'  => 'bg-blue-100 text-blue-800',
    'processing' => 'bg-indigo-100 text-indigo-800',
    'ready'      => 'bg-cyan-100 text-cyan-800',
    'dispatched' => 'bg-teal-100 text-teal-800',
    'completed'  => 'bg-emerald-100 text-emerald-800',
    'cancelled'  => 'bg-rose-100 text-rose-800',
];
$badge = $statusColors[$order['status']] ?? 'bg-slate-100 text-slate-800';
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order <?= e($order['order_ref']) ?> | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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

<main class="mx-auto max-w-4xl px-5 py-8">
  <div class="flex flex-wrap items-center gap-4">
    <a href="orders.php" class="text-sm font-semibold text-brand-accent">← Back to orders</a>
    <a href="invoice-pdf.php?id=<?= (int)$order['id'] ?>" class="text-sm font-semibold text-brand-accent">📄 Download Invoice PDF</a>
  </div>

  <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-3xl font-black">Order <?= e($order['order_ref']) ?></h1>
      <p class="mt-1 text-sm text-slate-500">Placed <?= e($order['created_at']) ?></p>
    </div>
    <span class="rounded-full px-4 py-2 text-sm font-bold <?= e($badge) ?>"><?= e(ucfirst($order['status'])) ?></span>
  </div>

  <div class="mt-6 grid gap-6 md:grid-cols-2">
    <section class="rounded-2xl bg-white p-6 shadow-sm">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Customer</h2>
      <p class="mt-3 text-lg font-bold"><?= e($order['customer_name']) ?></p>
      <p class="mt-1 text-sm text-slate-600"><?= e($order['customer_phone']) ?></p>
      <p class="mt-3 whitespace-pre-line text-sm text-slate-600"><?= e($order['delivery_address'] ?: '—') ?></p>
      <div class="mt-5 flex flex-wrap gap-2">
        <a target="_blank" rel="noopener"
           href="https://wa.me/<?= e(preg_replace('/\D+/', '', $order['customer_phone'])) ?>?text=<?= rawurlencode('Hello ' . $order['customer_name'] . ', this is TONNY regarding order ' . $order['order_ref'] . '.') ?>"
           class="rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover">WhatsApp customer</a>
        <a href="tel:<?= e($order['customer_phone']) ?>" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold">Call</a>
      </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Summary</h2>
      <dl class="mt-3 space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Items</dt><dd class="font-semibold"><?= count($items) ?></dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold">KES <?= number_format((float)$order['subtotal'], 2) ?></dd></div>
        <div class="flex justify-between border-t pt-2"><dt class="text-slate-500">Total</dt><dd class="text-xl font-black">KES <?= number_format((float)$order['total'], 2) ?></dd></div>
      </dl>
      <a href="<?= e($wa) ?>" target="_blank" rel="noopener"
         class="mt-5 block rounded-lg bg-brand-primary px-4 py-3 text-center text-sm font-bold text-brand-onPrimary hover:bg-brand-primaryHover">Re-send order on WhatsApp</a>
      <a href="invoice-pdf.php?id=<?= (int)$order['id'] ?>" target="_blank"
         class="mt-2 block rounded-lg bg-brand-accent px-4 py-3 text-center text-sm font-bold text-brand-onAccent hover:bg-brand-accentHover">📄 Download Invoice PDF</a>
    </section>
  </div>

  <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="border-b p-5"><h2 class="text-lg font-bold">Items</h2></div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr><th class="p-4">Product</th><th class="p-4">SKU</th><th class="p-4">Qty</th><th class="p-4">Unit price</th><th class="p-4">Line total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr class="border-t">
              <td class="p-4 font-semibold"><?= e($it['product_name']) ?></td>
              <td class="p-4 font-mono text-xs"><?= e($it['sku'] ?: '—') ?></td>
              <td class="p-4"><?= (int)$it['quantity'] ?></td>
              <td class="p-4">KES <?= number_format((float)$it['unit_price'], 2) ?></td>
              <td class="p-4 font-semibold">KES <?= number_format((float)$it['line_total'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?><tr><td colspan="5" class="p-6 text-center text-slate-500">No items recorded.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Update status</h2>
    <form method="post" action="orders.php" class="mt-3 flex flex-wrap items-center gap-3">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
      <select name="status" class="rounded-lg border border-slate-300 px-3 py-2">
        <?php foreach (['pending','confirmed','processing','ready','dispatched','completed','cancelled'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $order['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="rounded-lg bg-slate-900 px-5 py-2 font-semibold text-white">Save status</button>
    </form>
  </section>
</main>
</body></html>