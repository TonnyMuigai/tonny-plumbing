<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$pending = $_SESSION['mpesa_pending'] ?? null;
if (!$pending) {
    redirect('shop.php');
}

$orderRef = $pending['order_ref'];
$siteName = setting('site_name', 'TONNY');
$whatsapp = setting('whatsapp_number', '254723777601');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Waiting for payment | <?= e($siteName) ?></title>
<?= render_brand_head() ?>
</head>
<body class="bg-slate-50 text-slate-800">

<header class="bg-brand-primary text-brand-onPrimary">
  <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <?= render_logo('public') ?>
    <div class="flex items-center gap-2">
      <a class="rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover" target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20need%20help%20with%20my%20M-Pesa%20payment.">Need help?</a>
    </div>
  </nav>
</header>

<main class="mx-auto max-w-lg px-5 py-16">
  <div class="rounded-2xl bg-white p-8 text-center shadow">
    <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-brand-accent"></div>
    <h1 class="mt-5 text-2xl font-black">Check your phone</h1>
    <p class="mt-2 text-slate-600">
      We've sent an M-Pesa request to your phone for <strong>KES <?= number_format((float)$pending['amount'], 2) ?></strong>.
    </p>
    <p class="mt-4 text-sm text-slate-500">
      Enter your M-Pesa PIN to complete the payment for order <strong><?= e($orderRef) ?></strong>.
    </p>

    <div id="status-box" class="mt-6 rounded-lg bg-slate-100 p-4 text-sm text-slate-600">
      Waiting for confirmation…
    </div>

    <a href="order-success.php" class="mt-6 inline-block text-sm font-semibold text-brand-accent">I'll pay later</a>
  </div>
</main>

<script>
const orderRef = <?= json_encode($orderRef) ?>;
const statusBox = document.getElementById('status-box');
let attempts = 0;
const maxAttempts = 30;

function checkStatus() {
  attempts++;
  fetch('mpesa-status.php?ref=' + encodeURIComponent(orderRef))
    .then(r => r.json())
    .then(data => {
      if (data.status === 'completed') {
        statusBox.className = 'mt-6 rounded-lg bg-emerald-100 p-4 text-sm text-emerald-900';
        statusBox.textContent = 'Payment received! Redirecting…';
        setTimeout(() => { window.location.href = 'order-success.php'; }, 1500);
      } else if (data.status === 'failed' || data.status === 'cancelled') {
        statusBox.className = 'mt-6 rounded-lg bg-rose-100 p-4 text-sm text-rose-900';
        statusBox.textContent = data.message || 'Payment was not completed.';
        clearInterval(poll);
      } else if (attempts >= maxAttempts) {
        statusBox.textContent = 'Still waiting. You can check your order status later.';
        clearInterval(poll);
      }
    })
    .catch(() => {
      if (attempts >= maxAttempts) clearInterval(poll);
    });
}
const poll = setInterval(checkStatus, 4000);
checkStatus();
</script>

</body></html>