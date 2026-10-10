<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
    ? array_sum(array_map('intval', $_SESSION['cart']))
    : 0;

$ref     = $_SESSION['last_order_ref'] ?? '';
$total   = $_SESSION['last_order_total'] ?? 0;
$message = $_SESSION['last_order_message'] ?? '';
unset($_SESSION['last_order_ref'], $_SESSION['last_order_total'], $_SESSION['last_order_message']);

if (!$ref) redirect('shop.php');

$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
$wa = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($message);
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order placed | <?= e($siteName) ?></title>
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

<main class="mx-auto max-w-lg px-5 py-16">
  <div class="rounded-2xl bg-white p-8 text-center shadow">
    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl">✓</div>
    <h1 class="mt-5 text-2xl font-black">Order placed</h1>
    <p class="mt-3 text-slate-600">Your order reference is <strong><?= e($ref) ?></strong>.</p>
    <p class="mt-1 text-slate-600">Total: <strong>KES <?= number_format((float)$total, 2) ?></strong></p>

    <a href="pay-order.php?ref=<?= e($ref) ?>"
       class="mt-6 block rounded-xl bg-brand-accent px-5 py-4 font-bold text-brand-onAccent hover:bg-brand-accentHover">
      Pay KES <?= number_format((float)$total, 2) ?> with M-Pesa
    </a>

    <div class="mt-3 flex items-center gap-3 text-xs uppercase tracking-wide text-slate-400">
      <span class="h-px flex-1 bg-slate-200"></span>
      <span>or</span>
      <span class="h-px flex-1 bg-slate-200"></span>
    </div>

    <a href="<?= e($wa) ?>" target="_blank" rel="noopener"
       class="mt-3 block rounded-xl bg-brand-cta px-5 py-4 font-bold text-brand-onCta hover:bg-brand-ctaHover">
      Send order on WhatsApp
    </a>

    <p class="mt-4 text-sm text-slate-500">
      Your order is saved. Pay now with M-Pesa, or send it to us on WhatsApp and we'll confirm availability and delivery.
    </p>

    <div class="mt-4 flex justify-center gap-4 text-sm font-semibold">
      <a href="shop.php" class="text-brand-accent">Continue shopping</a>
      <a href="index.php" class="text-brand-accent">Back to homepage</a>
    </div>
  </div>
</main>

<footer class="mt-16 bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e($siteName) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>
</body></html>