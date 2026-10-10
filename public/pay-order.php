<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mpesa.php';

$ref = trim((string)($_GET['ref'] ?? ''));
if ($ref === '') {
    flash('error', 'Order reference is required.');
    redirect('shop.php');
}

$stmt = db()->prepare('SELECT * FROM shop_orders WHERE order_ref = ? LIMIT 1');
$stmt->execute([$ref]);
$order = $stmt->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('shop.php');
}

if ($order['status'] !== 'pending') {
    flash('error', 'This order is no longer awaiting payment.');
    redirect('shop.php');
}

$mpesaConfigured = mpesa_config()['consumer_key'] !== '' && mpesa_config()['callback_url'] !== '';
$notice = flash('success');
$error  = flash('error');

$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pay for <?= e($ref) ?> | <?= e($siteName) ?></title>
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
      <a href="order-success.php" class="rounded-lg border border-current/30 px-4 py-2 text-sm font-bold hover:bg-white/10">Back to order</a>
      <a class="rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20have%20an%20enquiry.">WhatsApp us</a>
    </div>
  </nav>
</header>

<main class="mx-auto max-w-lg px-5 py-16">
  <div class="rounded-2xl bg-white p-8 shadow">
    <h1 class="text-2xl font-black">Pay with M-Pesa</h1>
    <p class="mt-2 text-slate-600">Order <strong><?= e($ref) ?></strong></p>
    <p class="mt-4 text-4xl font-black">KES <?= number_format((float)$order['total'], 2) ?></p>

    <?php if (!$mpesaConfigured): ?>
      <p class="mt-5 rounded-lg bg-amber-100 p-3 text-amber-900 text-sm">
        M-Pesa is not configured yet. Please contact us on WhatsApp to complete payment.
      </p>
      <a target="_blank" rel="noopener"
         href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Hello, I want to pay for order ' . $ref . '.') ?>"
         class="mt-5 block rounded-xl bg-brand-cta px-5 py-4 text-center font-bold text-brand-onCta hover:bg-brand-ctaHover">
        Contact us on WhatsApp
      </a>
    <?php else: ?>
      <?php if ($notice): ?><p class="mt-5 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-900"><?= e($error) ?></p><?php endif; ?>

      <form method="post" action="mpesa-initiate.php" class="mt-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="order_ref" value="<?= e($ref) ?>">

        <label class="block text-sm font-semibold">M-Pesa phone number
          <input name="phone" required value="<?= e($order['customer_phone']) ?>"
                 placeholder="07xx xxx xxx"
                 class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
          <span class="mt-1 block text-xs font-normal text-slate-500">
            An STK prompt will be sent to this number. Use the M-Pesa PIN to confirm.
          </span>
        </label>

        <button class="w-full rounded-xl bg-brand-accent px-5 py-4 font-bold text-brand-onAccent hover:bg-brand-accentHover">
          Pay KES <?= number_format((float)$order['total'], 2) ?>
        </button>
      </form>
    <?php endif; ?>

    <a href="order-success.php" class="mt-5 inline-block text-sm font-semibold text-brand-accent">← Back</a>
  </div>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e($siteName) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>