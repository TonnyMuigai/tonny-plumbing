<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$id = auth_user_id();
$stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$me = $stmt->fetch();
if (!$me) { $_SESSION = []; session_destroy(); redirect('login.php'); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current  = (string)($_POST['current_password'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    $check = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1');
    $check->execute([$id]);
    $hash = (string)$check->fetchColumn();

    if (!password_verify($current, $hash))  $errors['current_password'] = 'Current password is incorrect.';
    if (strlen($password) < 12)             $errors['password'] = 'New password must be at least 12 characters.';
    if ($password !== $confirm)             $errors['confirm_password'] = 'New passwords do not match.';
    if (!$errors && $password === $current) $errors['password'] = 'New password must be different from the current one.';

    if (!$errors) {
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        log_event('user.password_changed', 'admin_user', $id);
        flash('success', 'Your password has been updated.');
        redirect('profile.php');
    }
}
$notice = flash('success');
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My profile | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
<?= render_brand_head() ?>
</head><body class="min-h-screen bg-slate-100 text-slate-800">
<header class="bg-slate-900 text-white">
  <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <a href="index.php" class="text-xl font-black"><?= e(setting('site_name','TONNY')) ?> <span class="font-normal text-slate-300">Admin</span></a>
    <nav class="flex flex-wrap items-center gap-5 text-sm">
      <?php foreach (nav_items() as $file => [$label, $visible]): if (!$visible) continue; ?>
        <a href="<?= e($file) ?>" class="hover:underline"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="profile.php" class="font-bold text-brand-accent">My profile</a>
      <form method="post" action="logout.php" class="inline">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="underline">Log out</button>
      </form>
    </nav>
  </div>
</header>

<main class="mx-auto max-w-2xl px-5 py-8">
  <h1 class="text-3xl font-black">My profile</h1>

  <?php if ($notice): ?><p class="mt-4 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>

  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Account</h2>
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
      <div><dt class="text-slate-500">Name</dt><dd class="font-semibold"><?= e($me['name']) ?></dd></div>
      <div><dt class="text-slate-500">Email</dt><dd class="font-semibold"><?= e($me['email']) ?></dd></div>
      <div><dt class="text-slate-500">Role</dt><dd class="font-semibold"><?= e(role_label($me['role'])) ?></dd></div>
    </dl>
    <p class="mt-4 text-xs text-slate-500">Ask the super admin if you need your name, email or role changed.</p>
  </section>

  <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold">Change password</h2>
    <form method="post" class="mt-4 space-y-5">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label class="block text-sm font-semibold">Current password *
        <input type="password" name="current_password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['current_password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['current_password']) ?></span><?php endif; ?>
      </label>

      <label class="block text-sm font-semibold">New password *
        <input type="password" name="password" minlength="12" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <span class="mt-1 block text-xs font-normal text-slate-500">Minimum 12 characters.</span>
        <?php if (!empty($errors['password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['password']) ?></span><?php endif; ?>
      </label>

      <label class="block text-sm font-semibold">Confirm new password *
        <input type="password" name="confirm_password" minlength="12" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['confirm_password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['confirm_password']) ?></span><?php endif; ?>
      </label>

      <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Update password</button>
    </form>
  </section>
</main>
</body></html>