<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();
$stats = [
 'new' => (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
 'today' => (int)db()->query("SELECT COUNT(*) FROM bookings WHERE preferred_date=CURDATE() AND status NOT IN ('cancelled')")->fetchColumn(),
 'orders' => (int)db()->query("SELECT COUNT(*) FROM shop_orders WHERE status IN ('pending','confirmed','processing','ready','dispatched')")->fetchColumn(),
 'stock' => (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty<=5")->fetchColumn(),
];
$recent = db()->query('SELECT id,booking_ref,customer_name,customer_phone,service_address,preferred_date,status,created_at FROM bookings ORDER BY created_at DESC LIMIT 8')->fetchAll();
$notice = flash('success');
$brand = setting('admin_brand_name', setting('site_name','TONNY') . ' Admin');
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard | <?= e($brand) ?></title>
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
  <h1 class="text-3xl font-black">Business overview</h1>
  <p class="mt-2 text-slate-500">Manage service requests, orders and products.</p>
  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>
  <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([
      ['New bookings',$stats['new'],'text-brand-accent'],
      ['Bookings scheduled today',$stats['today'],'text-blue-700'],
      ['Open orders',$stats['orders'],'text-amber-700'],
      ['Low-stock products',$stats['stock'],'text-rose-700']
    ] as $s): ?>
      <div class="rounded-2xl bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500"><?= e($s[0]) ?></p>
        <p class="mt-2 text-3xl font-black <?= e($s[2]) ?>"><?= (int)$s[1] ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-8 flex flex-wrap gap-3">
    <a href="bookings.php" class="rounded-lg bg-brand-primary px-4 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Manage bookings</a>
    <a href="orders.php" class="rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white">Manage orders</a>
    <a href="products.php" class="rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white">Manage products</a>
    <a href="../index.php" class="rounded-lg border bg-white px-4 py-3 font-semibold">View website</a>
  </div>
  <section class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="border-b p-5"><h2 class="text-lg font-bold">Recent booking requests</h2></div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr><th class="p-4">Reference</th><th class="p-4">Customer</th><th class="p-4">Location</th><th class="p-4">Date</th><th class="p-4">Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $b): ?>
            <tr class="border-t">
              <td class="p-4 font-semibold"><?= e($b['booking_ref']) ?></td>
              <td class="p-4"><?= e($b['customer_name']) ?><div class="text-xs text-slate-500"><?= e($b['customer_phone']) ?></div></td>
              <td class="p-4"><?= e($b['service_address']) ?></td>
              <td class="p-4"><?= e($b['preferred_date'] ?: 'Flexible') ?></td>
              <td class="p-4"><span class="rounded-full bg-slate-100 px-3 py-1"><?= e(str_replace('_',' ',$b['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="5" class="p-6 text-center text-slate-500">No bookings yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <p class="mt-6 text-xs text-slate-500">Quotes, invoices and payments are represented in the schema and will be implemented in the next stages.</p>
</main>
</body></html>