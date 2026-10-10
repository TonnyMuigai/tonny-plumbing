<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();
require_admin_or_above();

$textKeys = [
    'site_name'        => ['Site name',       'Shown in header. E.g. "TONNY".'],
    'site_name_suffix' => ['Site name accent','Coloured accent after site name. E.g. "." or " Plumbing".'],
    'admin_brand_name' => ['Admin brand name','Shown in admin header. E.g. "TONNY Admin".'],
    'hero_badge'       => ['Hero badge',      'Small pill above the headline.'],
    'hero_subtitle'    => ['Hero subtitle',   'Paragraph under the headline.'],
    'services_intro'   => ['Services intro',  'Intro under the services heading.'],
    'shop_intro'       => ['Shop intro',      'Intro under the shop heading.'],
    'booking_intro'    => ['Booking intro',   'Intro under the booking heading.'],
    'footer_tagline'   => ['Footer tagline',  'Line under the footer brand name.'],
    'whatsapp_number'  => ['WhatsApp number', 'Digits only, include country code. E.g. 254723777601.'],
];
$textareaKeys = ['hero_subtitle','services_intro','shop_intro','booking_intro','footer_tagline'];

$errors = [];
$notice = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save_text');

    if ($action === 'save_text') {
        foreach (array_keys($textKeys) as $k) {
            $val = trim((string)($_POST[$k] ?? ''));
            if ($k === 'whatsapp_number') {
                $val = preg_replace('/\D+/', '', $val);
                if ($val !== '' && (strlen($val) < 10 || strlen($val) > 15)) {
                    $errors[$k] = 'Enter a valid WhatsApp number (10-15 digits).';
                    continue;
                }
            }
            setting_set($k, $val);
        }
        if (isset($_POST['site_tagline_html'])) {
            $tag = strip_tags(trim((string)$_POST['site_tagline_html']), '<br><span><b><strong><i><em>');
            setting_set('site_tagline_html', $tag);
        }
        if (!$errors) {
            log_event('settings.text_saved', 'settings');
            flash('success', 'Settings saved.');
            redirect('settings.php');
        }
    }

    if ($action === 'save_colors_fonts') {
        $primary = branding_clean_hex((string)($_POST['color_primary'] ?? ''), '');
        $accent  = branding_clean_hex((string)($_POST['color_accent']  ?? ''), '');
        $cta     = branding_clean_hex((string)($_POST['color_cta']     ?? ''), '');
        if ($primary === '') $errors['color_primary'] = 'Enter a valid hex colour, e.g. #102a43.';
        if ($accent  === '') $errors['color_accent']  = 'Enter a valid hex colour, e.g. #0d9488.';
        if ($cta     === '') $errors['color_cta']     = 'Enter a valid hex colour, e.g. #10b981.';

        $fonts = branding_font_options();
        $fontBody    = (string)($_POST['font_body']    ?? 'system-ui');
        $fontHeading = (string)($_POST['font_heading'] ?? 'inherit');
        if (!array_key_exists($fontBody, $fonts))    $fontBody    = 'system-ui';
        if (!array_key_exists($fontHeading, $fonts)) $fontHeading = 'inherit';

        if (!$errors) {
            setting_set('color_primary', $primary);
            setting_set('color_accent',  $accent);
            setting_set('color_cta',     $cta);
            setting_set('font_body',     $fontBody);
            setting_set('font_heading',  $fontHeading);
            log_event('settings.colors_fonts_saved', 'settings', null, [
                'primary' => $primary, 'accent' => $accent, 'cta' => $cta,
                'font_body' => $fontBody, 'font_heading' => $fontHeading,
            ]);
            flash('success', 'Colours and fonts saved.');
            redirect('settings.php');
        }
    }

    if ($action === 'reset_colors') {
        setting_set('color_primary', '#102a43');
        setting_set('color_accent',  '#0d9488');
        setting_set('color_cta',     '#10b981');
        setting_set('font_body',     'system-ui');
        setting_set('font_heading',  'inherit');
        flash('success', 'Colours and fonts reset to defaults.');
        redirect('settings.php');
    }

    if ($action === 'save_logo') {
        try {
            $p = handle_branding_upload($_FILES['logo_image'] ?? [], setting('logo_image_path') ?: null);
            setting_set('logo_image_path', $p ?? '');
            log_event('settings.logo_saved', 'settings');
            flash('success', 'Logo updated.');
        } catch (RuntimeException $ex) {
            $errors['logo_image'] = $ex->getMessage();
        }
        if (!$errors) redirect('settings.php');
    }

    if ($action === 'remove_logo') {
        $old = setting('logo_image_path');
        if ($old !== '') {
            $f = dirname(__DIR__, 2) . '/public/' . ltrim($old, '/');
            if (is_file($f)) @unlink($f);
        }
        setting_set('logo_image_path', '');
        flash('success', 'Logo removed.');
        redirect('settings.php');
    }

    if ($action === 'save_hero_bg') {
        try {
            $p = handle_branding_upload($_FILES['hero_background_image'] ?? [], setting('hero_background_image_path') ?: null);
            setting_set('hero_background_image_path', $p ?? '');
            log_event('settings.hero_bg_saved', 'settings');
            flash('success', 'Hero background updated.');
        } catch (RuntimeException $ex) {
            $errors['hero_background_image'] = $ex->getMessage();
        }
        if (!$errors) redirect('settings.php');
    }

    if ($action === 'remove_hero_bg') {
        $old = setting('hero_background_image_path');
        if ($old !== '') {
            $f = dirname(__DIR__, 2) . '/public/' . ltrim($old, '/');
            if (is_file($f)) @unlink($f);
        }
        setting_set('hero_background_image_path', '');
        flash('success', 'Hero background removed.');
        redirect('settings.php');
    }

    if ($action === 'save_hero_overlay') {
        $v = max(0, min(100, (int)($_POST['hero_background_overlay'] ?? 60)));
        setting_set('hero_background_overlay', (string)$v);
        flash('success', 'Overlay saved.');
        redirect('settings.php');
    }

    if ($action === 'save_favicon') {
        try {
            $p = handle_branding_upload($_FILES['favicon_image'] ?? [], setting('favicon_image_path') ?: null);
            setting_set('favicon_image_path', $p ?? '');
            flash('success', 'Favicon updated.');
        } catch (RuntimeException $ex) {
            $errors['favicon_image'] = $ex->getMessage();
        }
        if (!$errors) redirect('settings.php');
    }

    if ($action === 'remove_favicon') {
        $old = setting('favicon_image_path');
        if ($old !== '') {
            $f = dirname(__DIR__, 2) . '/public/' . ltrim($old, '/');
            if (is_file($f)) @unlink($f);
        }
        setting_set('favicon_image_path', '');
        flash('success', 'Favicon removed.');
        redirect('settings.php');
    }
}

$currentLogo        = setting('logo_image_path');
$currentFavicon     = setting('favicon_image_path');
$currentHeroBg      = setting('hero_background_image_path');
$currentHeroOverlay = max(0, min(100, (int)setting('hero_background_overlay', '60')));
$currentPrimary     = setting('color_primary', '#102a43');
$currentAccent      = setting('color_accent', '#0d9488');
$currentCta         = setting('color_cta', '#10b981');
$currentFontBody    = setting('font_body', 'system-ui');
$currentFontHeading = setting('font_heading', 'inherit');
$fonts              = branding_font_options();
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site settings | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
<main class="mx-auto max-w-5xl px-5 py-8">
  <h1 class="text-3xl font-black">Site settings</h1>
  <p class="mt-2 text-slate-500">Logo, colours, fonts, hero and text — no file editing needed.</p>

  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>

  <!-- COLORS & FONTS -->
  <section class="mt-8 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Colors &amp; fonts</h2>
    <p class="mt-1 text-sm text-slate-500">Pick three brand colours and two fonts.</p>

    <form method="post" class="mt-4 space-y-5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_colors_fonts">

      <div class="grid gap-5 sm:grid-cols-3">
        <?php foreach ([
          ['color_primary','Primary','Header, footer, brand areas.', $currentPrimary],
          ['color_accent','Accent','Buttons, links, section labels.', $currentAccent],
          ['color_cta','Call-to-action','Bright "Order" / WhatsApp buttons.', $currentCta],
        ] as [$k, $label, $help, $val]): ?>
          <label class="block text-sm font-semibold"><?= e($label) ?>
            <span class="mt-2 flex items-center gap-2 rounded-lg border border-slate-300 px-2 py-2">
              <input type="color"
                     id="<?= e($k) ?>_picker"
                     name="<?= e($k) ?>"
                     value="<?= e($val !== '' ? $val : '#000000') ?>"
                     class="h-10 w-14 cursor-pointer rounded border-0 bg-transparent"
                     oninput="document.getElementById('<?= e($k) ?>_text').value = this.value">
              <input type="text"
                     id="<?= e($k) ?>_text"
                     value="<?= e($val !== '' ? $val : '#000000') ?>"
                     readonly
                     class="w-full bg-transparent font-mono text-xs">
            </span>
            <span class="mt-1 block text-xs font-normal text-slate-500"><?= e($help) ?></span>
            <?php if (!empty($errors[$k])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors[$k]) ?></span><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Body font
          <select name="font_body" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal">
            <?php foreach ($fonts as $fk => $fl): ?><option value="<?= e($fk) ?>" <?= $currentFontBody===$fk?'selected':'' ?>><?= e($fl) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="block text-sm font-semibold">Heading font
          <select name="font_heading" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal">
            <?php foreach ($fonts as $fk => $fl): ?><option value="<?= e($fk) ?>" <?= $currentFontHeading===$fk?'selected':'' ?>><?= e($fl) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="flex flex-wrap gap-3">
        <button class="rounded-lg bg-teal-700 px-6 py-3 font-semibold text-white">Save colours &amp; fonts</button>
      </div>
    </form>
    <form method="post" class="mt-3" onsubmit="return confirm('Reset colours and fonts to defaults?')">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="reset_colors">
      <button class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-semibold">Reset to defaults</button>
    </form>
  </section>

  <!-- LOGO -->
  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Logo</h2>
    <p class="mt-1 text-sm text-slate-500">PNG, SVG or WEBP. Falls back to text if not set.</p>
    <div class="mt-4 flex flex-wrap items-start gap-6">
      <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
        <?php if ($currentLogo !== ''): ?><img src="../<?= e($currentLogo) ?>" alt="Logo" class="max-h-16">
        <?php else: ?><p class="text-sm text-slate-500">Text logo in use:<br><span class="mt-1 inline-block text-xl font-black"><?= e(setting('site_name','TONNY')) ?><span class="text-teal-600"><?= e(setting('site_name_suffix','.')) ?></span></span></p><?php endif; ?>
      </div>
      <div class="flex-1 space-y-3">
        <form method="post" enctype="multipart/form-data" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_logo">
          <input type="file" name="logo_image" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <?php if (!empty($errors['logo_image'])): ?><span class="block text-xs text-rose-700"><?= e($errors['logo_image']) ?></span><?php endif; ?>
          <button class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white">Upload logo</button>
        </form>
        <?php if ($currentLogo !== ''): ?>
          <form method="post" onsubmit="return confirm('Remove logo?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_logo">
            <button class="rounded-lg border border-rose-300 px-5 py-2 text-sm font-semibold text-rose-700">Remove logo</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- HERO BACKGROUND -->
  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Hero background image</h2>
    <p class="mt-1 text-sm text-slate-500">Optional photo behind the homepage headline. 1920x1080 or wider, under 1 MB.</p>
    <div class="mt-4 flex flex-wrap items-start gap-6">
      <div class="w-64 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-2">
        <?php if ($currentHeroBg !== ''): ?><img src="../<?= e($currentHeroBg) ?>" alt="Hero" class="w-full rounded">
        <?php else: ?><div class="grid h-32 w-full place-items-center rounded bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900 text-xs text-slate-200">Default gradient</div><?php endif; ?>
      </div>
      <div class="flex-1 space-y-3">
        <form method="post" enctype="multipart/form-data" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_hero_bg">
          <input type="file" name="hero_background_image" accept="image/png,image/jpeg,image/webp" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <?php if (!empty($errors['hero_background_image'])): ?><span class="block text-xs text-rose-700"><?= e($errors['hero_background_image']) ?></span><?php endif; ?>
          <button class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white">Upload hero background</button>
        </form>
        <?php if ($currentHeroBg !== ''): ?>
          <form method="post" onsubmit="return confirm('Remove hero background?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_hero_bg">
            <button class="rounded-lg border border-rose-300 px-5 py-2 text-sm font-semibold text-rose-700">Remove hero background</button>
          </form>
        <?php endif; ?>
        <form method="post" class="space-y-3 border-t pt-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_hero_overlay">
          <label class="block text-sm font-semibold">Text overlay darkness
            <input type="range" name="hero_background_overlay" min="0" max="100" step="5" value="<?= (int)$currentHeroOverlay ?>" oninput="document.getElementById('ov-val').textContent=this.value+'%'" class="mt-2 w-full">
            <span class="mt-1 block text-xs font-normal text-slate-500">Higher = darker = better contrast. Current: <strong id="ov-val"><?= (int)$currentHeroOverlay ?>%</strong></span>
          </label>
          <button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Save overlay</button>
        </form>
      </div>
    </div>
  </section>

  <!-- FAVICON -->
  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Favicon</h2>
    <p class="mt-1 text-sm text-slate-500">Browser tab icon. ICO, PNG or SVG. 32x32 or 64x64.</p>
    <div class="mt-4 flex flex-wrap items-start gap-6">
      <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
        <?php if ($currentFavicon !== ''): ?><img src="../<?= e($currentFavicon) ?>" alt="Favicon" class="h-10 w-10">
        <?php else: ?><p class="text-sm text-slate-500">No favicon set.</p><?php endif; ?>
      </div>
      <div class="flex-1 space-y-3">
        <form method="post" enctype="multipart/form-data" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_favicon">
          <input type="file" name="favicon_image" accept="image/x-icon,image/png,image/svg+xml,image/vnd.microsoft.icon" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <?php if (!empty($errors['favicon_image'])): ?><span class="block text-xs text-rose-700"><?= e($errors['favicon_image']) ?></span><?php endif; ?>
          <button class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white">Upload favicon</button>
        </form>
        <?php if ($currentFavicon !== ''): ?>
          <form method="post" onsubmit="return confirm('Remove favicon?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_favicon">
            <button class="rounded-lg border border-rose-300 px-5 py-2 text-sm font-semibold text-rose-700">Remove favicon</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- TEXT -->
  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Text content</h2>
    <form method="post" class="mt-4 space-y-5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_text">

      <?php foreach ($textKeys as $key => $meta): ?>
        <label class="block text-sm font-semibold">
          <?= e($meta[0]) ?>
          <?php if (in_array($key, $textareaKeys, true)): ?>
            <textarea name="<?= e($key) ?>" rows="2" maxlength="600" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal"><?= e(setting($key)) ?></textarea>
          <?php else: ?>
            <input name="<?= e($key) ?>" maxlength="200" value="<?= e(setting($key)) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal">
          <?php endif; ?>
          <span class="mt-1 block text-xs font-normal text-slate-500"><?= e($meta[1]) ?></span>
          <?php if (!empty($errors[$key])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors[$key]) ?></span><?php endif; ?>
        </label>
      <?php endforeach; ?>

      <label class="block text-sm font-semibold">
        Hero headline
        <textarea name="site_tagline_html" rows="2" maxlength="300" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-mono text-sm font-normal"><?= e(setting('site_tagline_html')) ?></textarea>
        <span class="mt-1 block text-xs font-normal text-slate-500">Simple HTML allowed: &lt;br&gt;, &lt;span class="text-teal-300"&gt;.</span>
      </label>

      <button class="rounded-lg bg-teal-700 px-6 py-3 font-semibold text-white">Save all text settings</button>
    </form>
  </section>
</main>
</body></html>