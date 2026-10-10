<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$statuses = ['pending','confirmed','processing','ready','dispatched','completed','cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = (string)($_POST['status'] ?? '');
    if (!$id || !in_array($status, $statuses, true)) {
        flash('success', 'Invalid status update.');
        redirect('orders.php');
    }
    db()->prepare('UPDATE shop_orders SET status=? WHERE id=?')->execute([$status, $id]);
    log_event('order.status_changed', 'shop_order', $id, ['status' => $status]);
    flash('success', 'Order updated.');
    redirect('orders.php');
}

$filter = (string)($_GET['status'] ?? '');
$sql = 'SELECT o.*, (SELECT COUNT(*) FROM shop_order_items i WHERE i.order_id=o.id) AS item_count FROM shop_orders o';
$params = [];
if ($filter !== '' && in_array($filter, $statuses, true)) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$notice = flash('success');
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orders | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
  <h1 class="text-3xl font-black">Shop orders</h1>

  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>

  <div class="mt-6 flex flex-wrap gap-2">
    <a href="orders.php" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold <?= $filter === '' ? 'bg-slate-900 text-white border-slate-900' : '' ?>">All</a>
    <?php foreach ($statuses as $s): ?>
      <a href="orders.php?status=<?= e($s) ?>" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold <?= $filter === $s ? 'bg-slate-900 text-white border-slate-900' : '' ?>"><?= e(ucfirst($s)) ?></a>
    <?php endforeach; ?>
  </div>

  <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr>
            <th class="p-4">Reference</th>
            <th class="p-4">Customer</th>
            <th class="p-4">Address</th>
            <th class="p-4">Items</th>
            <th class="p-4">Total (KES)</th>
            <th class="p-4">Created</th>
            <th class="p-4">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr class="border-t align-top">
              <td class="p-4 font-semibold">
                <a href="order-view.php?id=<?= (int)$o['id'] ?>" class="text-brand-accent hover:underline"><?= e($o['order_ref']) ?></a>
              </td>
              <td class="p-4">
                <?= e($o['customer_name']) ?>
                <div class="text-xs text-slate-500"><?= e($o['customer_phone']) ?></div>
              </td>
              <td class="p-4"><?= e($o['delivery_address'] ?? '—') ?></td>
              <td class="p-4"><?= (int)$o['item_count'] ?></td>
              <td class="p-4 font-semibold"><?= number_format((float)$o['total'], 2) ?></td>
              <td class="p-4 text-xs text-slate-500"><?= e($o['created_at']) ?></td>
              <td class="p-4">
                <form method="post" class="flex items-center gap-2">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <select name="status" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">
                    <?php foreach ($statuses as $s): ?>
                      <option value="<?= e($s) ?>" <?= $o['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="rounded bg-slate-900 px-3 py-1 text-xs font-semibold text-white">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$orders): ?>
            <tr><td colspan="7" class="p-8 text-center text-slate-500">No orders<?= $filter !== '' ? ' with status "'.$filter.'"' : '' ?> yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body></html>