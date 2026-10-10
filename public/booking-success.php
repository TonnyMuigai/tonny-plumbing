<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$ref     = $_SESSION['last_booking_ref'] ?? '';
$message = $_SESSION['last_booking_message'] ?? '';
unset($_SESSION['last_booking_ref'], $_SESSION['last_booking_message']);
if (!$ref || !$message) redirect('index.php');

$whatsapp = setting('whatsapp_number', '254723777601');
$siteName = setting('site_name', 'TONNY');
$wa = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($message);
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking received | <?= e($siteName) ?></title>
<?= render_brand_head() ?>
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 p-5">

<main class="w-full max-w-lg rounded-2xl bg-white p-8 text-center shadow">
  <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl">✓</div>
  <h1 class="mt-5 text-2xl font-black">Request saved</h1>
  <p class="mt-3 text-slate-600">Your reference is <strong><?= e($ref) ?></strong>. Your booking is not confirmed until <?= e($siteName) ?> confirms availability.</p>

  <a href="<?= e($wa) ?>" target="_blank" rel="noopener"
     class="mt-6 block rounded-xl bg-brand-cta px-5 py-4 font-bold text-brand-onCta hover:bg-brand-ctaHover">
    Continue to WhatsApp
  </a>

  <p class="mt-3 text-xs text-slate-500">WhatsApp will open with your message prefilled. Press Send in WhatsApp to deliver it.</p>

  <a href="index.php" class="mt-5 inline-block text-sm font-semibold text-brand-accent">Return to homepage</a>
</main>

</body></html>