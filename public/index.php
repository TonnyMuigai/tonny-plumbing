<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$services = db()->query('SELECT id,name,description FROM service_types WHERE is_active=1 ORDER BY id')->fetchAll();
$featured = db()->query('SELECT id, sku, name, slug, category, price, stock_qty, image_path FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 6')->fetchAll();
$cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum(array_map('intval', $_SESSION['cart'])) : 0;
$success = flash('success');
$error = flash('error');
$whatsapp = setting('whatsapp_number', '254723777601');
$heroBg = setting('hero_background_image_path');
$heroOverlay = max(0, min(100, (int)setting('hero_background_overlay', '60')));

// Rotating panel images
$rotators = [
    ['file' => 'uploads/branding/hero-1.jpg', 'icon' => '🚛', 'title' => 'Exhauster & septic',   'sub' => 'Fast, clean, fully equipped'],
    ['file' => 'uploads/branding/hero-2.jpg', 'icon' => '🌊', 'title' => 'Sewerage & drainage',  'sub' => 'Clearing, repair, maintenance'],
    ['file' => 'uploads/branding/hero-3.jpg', 'icon' => '🔧', 'title' => 'Plumbing repairs',     'sub' => 'Component replacement & fixes'],
    ['file' => 'uploads/branding/hero-4.jpg', 'icon' => '🧰', 'title' => 'Plumbing supplies',    'sub' => 'Pipes, fittings, taps & valves'],
];
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(setting('site_name','TONNY') . ' Plumbing & Sanitation') ?></title>
<meta name="description" content="<?= e(setting('hero_subtitle')) ?>">
<?= render_brand_head() ?>
<?= render_animations() ?>
</head>
<body class="bg-slate-50 text-slate-800">

<header class="bg-brand-primary text-brand-onPrimary">
  <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <?= render_logo('public') ?>
    <div class="hidden gap-6 text-sm md:flex">
      <a href="#services" class="hover:opacity-80">Services</a>
      <a href="shop.php" class="hover:opacity-80">Plumbing supplies</a>
      <a href="#booking" class="hover:opacity-80">Book a service</a>
    </div>
    <div class="flex items-center gap-2">
      <a href="cart.php" class="rounded-lg border border-white/30 px-4 py-2 text-sm font-bold hover:bg-white/10">Cart (<?= (int)$cartCount ?>)</a>
      <a class="btn-shine rounded-lg bg-brand-cta px-4 py-2 text-sm font-bold text-brand-onCta hover:bg-brand-ctaHover"
         target="_blank" rel="noopener"
         href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20would%20like%20to%20make%20an%20enquiry.">WhatsApp us</a>
    </div>
  </nav>
</header>

<main>

<?php if ($heroBg !== ''): ?>
<section class="relative bg-cover bg-center text-white" style="background-image:url('<?= e($heroBg) ?>')">
  <div class="absolute inset-0" style="background-color:rgba(15,23,42,<?= number_format($heroOverlay / 100, 2, '.', '') ?>)"></div>
  <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-5 py-20 md:grid-cols-2 md:py-28">
<?php else: ?>
<section class="anim-gradient bg-gradient-to-br from-slate-900 via-brand-primary to-brand-accent text-white">
  <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-20 md:grid-cols-2 md:py-28">
<?php endif; ?>

    <!-- Left: hero copy (fast, auto-plays on load) -->
    <div data-hero>
      <p class="mb-4 inline-flex rounded-full border border-white/30 bg-white/10 px-3 py-1 text-sm">
        <?= e(setting('hero_badge')) ?>
      </p>
      <h1 class="text-4xl font-black leading-tight md:text-6xl">
        <?= setting('site_tagline_html') ?>
      </h1>
      <p class="mt-6 max-w-xl text-lg leading-8 opacity-90">
        <?= e(setting('hero_subtitle')) ?>
      </p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="#booking" class="btn-shine rounded-xl bg-brand-accent px-6 py-3 font-bold text-brand-onAccent hover:bg-brand-accentHover">Request a service</a>
        <a href="shop.php" class="rounded-xl border border-white/30 px-6 py-3 font-bold hover:bg-white/10">Explore supplies</a>
      </div>
      <p class="mt-6 text-sm opacity-80">Serving homes, businesses, estates and construction projects.</p>
    </div>

    <!-- Right: rotating image panel — appears immediately, no entrance delay -->
    <div class="anim-float">
      <div class="rounded-3xl border border-white/15 bg-white/10 p-2 shadow-2xl backdrop-blur">
        <div class="rotator relative h-72 w-full overflow-hidden rounded-2xl sm:h-80 md:h-96" data-rotator data-rotator-interval="4500">
          <?php foreach ($rotators as $i => $r): $file = $r['file']; $exists = is_file(dirname(__DIR__) . '/public/' . $file); ?>
            <div class="rot-slide <?= $i === 0 ? 'is-active' : '' ?>"
                 style="<?= $exists ? "background-image:url('" . e($file) . "')" : "background:linear-gradient(135deg,#0f172a,#0d9488)" ?>">
              <div class="absolute inset-0" style="background:linear-gradient(to top, rgba(15,23,42,.85), rgba(15,23,42,.15) 55%, rgba(15,23,42,0))"></div>
              <div class="relative flex h-full flex-col justify-end p-5 text-white">
                <div class="mb-2 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-white/15 backdrop-blur text-2xl"><?= $r['icon'] ?></div>
                <p class="text-xl font-black leading-tight"><?= e($r['title']) ?></p>
                <p class="mt-1 text-sm opacity-90"><?= e($r['sub']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="rot-dots mt-3 pb-2">
          <?php foreach ($rotators as $i => $r): ?>
            <button type="button" aria-label="Show slide <?= $i + 1 ?>" class="<?= $i === 0 ? 'is-active' : '' ?>"></button>
          <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap gap-2 px-2 pb-3">
          <a href="#booking" class="btn-shine flex-1 rounded-lg bg-brand-accent px-4 py-3 text-center font-semibold text-brand-onAccent hover:bg-brand-accentHover">Book a service →</a>
          <a target="_blank" rel="noopener" href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20have%20a%20question." class="flex-1 rounded-lg border border-white/30 px-4 py-3 text-center font-semibold hover:bg-white/10">Ask on WhatsApp</a>
        </div>
      </div>
    </div>

  </div>
</section>

<?php if ($success): ?><div class="mx-auto mt-5 max-w-4xl rounded-xl bg-emerald-100 p-4 text-emerald-900"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-auto mt-5 max-w-4xl rounded-xl bg-rose-100 p-4 text-rose-900"><?= e($error) ?></div><?php endif; ?>

<section id="services" class="mx-auto max-w-7xl px-5 py-16">
  <div data-anim class="max-w-2xl">
    <p class="font-bold uppercase tracking-widest text-brand-accent">What we do</p>
    <h2 class="mt-2 text-3xl font-black md:text-4xl">Services that keep things flowing</h2>
    <p class="mt-4 text-slate-600"><?= e(setting('services_intro')) ?></p>
  </div>
  <div data-stagger class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <?php $cards = [
      ['🚛','Exhauster services','Septic tank and pit emptying requests, with scheduling based on location and capacity.'],
      ['🌊','Sewerage services','Sewer and drainage inspection, maintenance and service requests.'],
      ['🔧','Plumbing repairs','General plumbing maintenance and component replacement.'],
      ['🧰','Plumbing supplies','Pipes, fittings, valves, taps and other plumbing essentials.']
    ]; foreach ($cards as $card): ?>
    <article class="hover-lift rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="text-3xl"><?= $card[0] ?></div>
      <h3 class="mt-4 text-lg font-bold"><?= e($card[1]) ?></h3>
      <p class="mt-2 text-sm leading-6 text-slate-600"><?= e($card[2]) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<section id="shop" class="bg-white py-14">
  <div class="mx-auto max-w-7xl px-5">
    <div data-anim class="flex flex-col items-start justify-between gap-5 md:flex-row md:items-end">
      <div>
        <p class="font-bold uppercase tracking-widest text-brand-accent">Plumbing shop</p>
        <h2 class="mt-2 text-3xl font-black">Components for your next project</h2>
        <p class="mt-3 max-w-2xl text-slate-600"><?= e(setting('shop_intro')) ?></p>
      </div>
      <a href="shop.php" class="btn-shine rounded-xl bg-brand-primary px-5 py-3 font-bold text-brand-onPrimary">View all products →</a>
    </div>
    <?php if ($featured): ?>
      <div data-stagger class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($featured as $f): ?>
          <a href="product.php?slug=<?= e(urlencode($f['slug'])) ?>" class="hover-lift group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if (!empty($f['image_path'])): ?>
              <img src="<?= e($f['image_path']) ?>" alt="<?= e($f['name']) ?>" class="h-44 w-full object-cover transition duration-500 group-hover:scale-105">
            <?php else: ?>
              <div class="grid h-44 w-full place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-5xl">🔧</div>
            <?php endif; ?>
            <div class="p-5">
              <?php if (!empty($f['category'])): ?>
                <p class="text-xs font-bold uppercase tracking-wide text-brand-accent"><?= e($f['category']) ?></p>
              <?php endif; ?>
              <p class="mt-1 font-bold leading-snug group-hover:text-brand-accent"><?= e($f['name']) ?></p>
              <p class="mt-2 text-xl font-black">KES <?= number_format((float)$f['price'], 2) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="mt-8 rounded-xl bg-slate-100 p-6 text-center text-slate-500">Products will appear here once they're added in the admin panel.</p>
    <?php endif; ?>
  </div>
</section>

<section id="booking" class="bg-slate-100 py-16">
  <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-2">
    <div data-anim>
      <p class="font-bold uppercase tracking-widest text-brand-accent">Book a service</p>
      <h2 class="mt-2 text-3xl font-black md:text-4xl">Tell us what you need</h2>
      <p class="mt-4 leading-7 text-slate-600"><?= e(setting('booking_intro')) ?></p>
      <div class="mt-8 space-y-4">
        <div class="flex gap-3"><span class="rounded-lg bg-white p-3">📍</span><div><h3 class="font-bold">Share the service location</h3><p class="text-sm text-slate-600">Include the estate, road or a nearby landmark.</p></div></div>
        <div class="flex gap-3"><span class="rounded-lg bg-white p-3">💬</span><div><h3 class="font-bold">Confirm over WhatsApp</h3><p class="text-sm text-slate-600">We'll agree on availability, pricing and timing.</p></div></div>
      </div>
    </div>
    <form data-anim action="book.php" method="post" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Your name<input required maxlength="150" name="name" autocomplete="name" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal outline-none focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/20"></label>
        <label class="block text-sm font-semibold">Phone number<input required maxlength="30" name="phone" autocomplete="tel" placeholder="07xx xxx xxx" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal outline-none focus:border-brand-accent focus:ring-2 focus:ring-brand-accent/20"></label>
        <label class="block text-sm font-semibold sm:col-span-2">Service needed<select required name="service_type_id" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 font-normal"><?php foreach ($services as $service): ?><option value="<?= (int)$service['id'] ?>"><?= e($service['name']) ?></option><?php endforeach; ?></select></label>
        <label class="block text-sm font-semibold sm:col-span-2">Service location<input required maxlength="255" name="address" placeholder="Estate, road, town and landmark" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal"></label>
        <label class="block text-sm font-semibold">Preferred date<input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal"></label>
        <label class="block text-sm font-semibold">Preferred time<select name="preferred_time" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 font-normal"><option value="">Flexible</option><option>Morning</option><option>Afternoon</option><option>Evening</option></select></label>
        <label class="block text-sm font-semibold sm:col-span-2">Extra details<textarea name="details" maxlength="2000" rows="3" placeholder="Tank size, access details, issue description..." class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal"></textarea></label>
      </div>
      <button class="btn-shine mt-6 w-full rounded-xl bg-brand-accent px-5 py-4 font-bold text-brand-onAccent hover:bg-brand-accentHover" type="submit">Submit request & continue to WhatsApp</button>
      <p class="mt-3 text-center text-xs text-slate-500">Please don't include sensitive personal or payment information in the details field.</p>
    </form>
  </div>
</section>

</main>

<footer class="bg-brand-primary px-5 py-8 text-center text-sm text-brand-onPrimary/80">
  <p class="font-bold text-brand-onPrimary"><?= e(setting('site_name','TONNY')) ?> Plumbing & Sanitation</p>
  <p class="mt-2"><?= e(setting('footer_tagline')) ?></p>
  <p class="mt-4">© <?= date('Y') ?> <?= e(setting('site_name','TONNY')) ?>. All rights reserved.</p>
  <a class="mt-3 inline-block underline" href="admin/login.php">Admin login</a>
</footer>

<a aria-label="Chat with us on WhatsApp" target="_blank" rel="noopener"
   href="https://wa.me/<?= e($whatsapp) ?>?text=Hello%2C%20I%20need%20assistance."
   class="pulse-ring fixed bottom-5 right-5 rounded-full bg-brand-cta px-5 py-4 font-bold text-brand-onCta shadow-xl hover:bg-brand-ctaHover">
  WhatsApp
</a>

</body></html>