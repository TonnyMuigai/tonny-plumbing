<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();
$statuses = ['new','contacted','confirmed','in_progress','completed','cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = (string)($_POST['status'] ?? '');
    $notes = trim((string)($_POST['admin_notes'] ?? ''));
    if (!$id || !in_array($status, $statuses, true) || mb_strlen($notes) > 5000) {
        flash('success','Invalid update. Please check the form.');
        redirect('bookings.php');
    }
    $stmt = db()->prepare('UPDATE bookings SET status=?, admin_notes=? WHERE id=?');
    $stmt->execute([$status, $notes !== '' ? $notes : null, $id]);
    log_event('booking.updated','booking',$id,['status'=>$status]);
    flash('success','Booking updated.');
    redirect('bookings.php');
}
$rows = db()->query('SELECT b.*,s.name AS service_name FROM bookings b LEFT JOIN service_types s ON s.id=b.service_type_id ORDER BY b.created_at DESC LIMIT 200')->fetchAll();
$notice = flash('success');
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bookings | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
<?= render_brand_head() ?>
</head><body class="min-h-screen bg-slate-100">
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
  <h1 class="text-3xl font-black">Service bookings</h1>
  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3"><?= e($notice) ?></p><?php endif; ?>
  <div class="mt-6 space-y-4">
    <?php foreach ($rows as $b): ?>
      <article class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex flex-wrap justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-wide text-brand-accent"><?= e($b['booking_ref']) ?> · <?= e($b['service_name'] ?? 'Service') ?></p>
            <h2 class="mt-1 text-lg font-bold"><?= e($b['customer_name']) ?></h2>
            <p class="text-sm text-slate-500"><?= e($b['customer_phone']) ?> · <?= e($b['service_address']) ?></p>
            <p class="mt-1 text-sm text-slate-500">Preferred: <?= e($b['preferred_date'] ?: 'Flexible') ?> <?= e($b['preferred_time'] ?: '') ?></p>
            <?php if ($b['details']): ?><p class="mt-3 whitespace-pre-line text-sm"><?= e($b['details']) ?></p><?php endif; ?>
          </div>
          <a class="h-fit rounded-lg bg-brand-cta px-3 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover" target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $b['customer_phone'])) ?>?text=<?= rawurlencode('Hello '. $b['customer_name'] . ', this is TONNY regarding booking '.$b['booking_ref'].'.') ?>">WhatsApp customer</a>
        </div>
        <form method="post" class="mt-5 grid gap-3 border-t pt-4 md:grid-cols-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <label class="text-sm font-semibold">Status
            <select name="status" class="mt-1 w-full rounded-lg border p-2">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $b['status']===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="text-sm font-semibold md:col-span-2">Internal notes
            <textarea name="admin_notes" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border p-2 font-normal"><?= e($b['admin_notes']) ?></textarea>
          </label>
          <div class="md:col-span-3">
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Save update</button>
          </div>
        </form>
      </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="rounded-xl bg-white p-8 text-center text-slate-500">No bookings found.</p><?php endif; ?>
  </div>
</main>
</body></html>