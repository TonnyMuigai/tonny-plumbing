<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { flash('error', 'User not found.'); redirect('users.php'); }

$stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { flash('error', 'User not found.'); redirect('users.php'); }
if ($user['role'] === ROLE_SUPER_ADMIN) {
    flash('error', 'Use your own profile to change the super admin password.');
    redirect('profile.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 12) $errors['password'] = 'Password must be at least 12 characters.';
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';

    if (!$errors) {
        db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        log_event('user.password_reset', 'admin_user', $id, ['email' => $user['email']]);
        flash('success', 'Password updated for ' . $user['name'] . '.');
        redirect('users.php');
    }
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change password | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
<?= render_brand_head() ?>
</head><body class="min-h-screen bg-slate-100 text-slate-800">
<header class="bg-slate-900 text-white">
  <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
    <a href="index.php" class="text-xl font-black"><?= e(setting('site_name','TONNY')) ?> <span class="font-normal text-slate-300">Admin</span></a>
    <nav class="flex flex-wrap items-center gap-5 text-sm">
      <?php foreach (nav_items() as $file => [$label, $visible]): if (!$visible) continue; ?>
        <a href="<?= e($file) ?>" class="<?= $file === 'users.php' ? 'font-bold text-brand-accent' : 'hover:underline' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <form method="post" action="logout.php" class="inline">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="underline">Log out</button>
      </form>
    </nav>
  </div>
</header>

<main class="mx-auto max-w-2xl px-5 py-8">
  <a href="users.php" class="text-sm font-semibold text-brand-accent">← Back to users</a>
  <h1 class="mt-3 text-3xl font-black">Reset password</h1>
  <p class="mt-2 text-slate-500">Setting a new password for <strong><?= e($user['name']) ?></strong> (<?= e($user['email']) ?>).</p>

  <form method="post" class="mt-6 space-y-5 rounded-2xl bg-white p-6 shadow-sm md:p-8">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <label class="block text-sm font-semibold">New password *
      <input type="password" name="password" minlength="12" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <span class="mt-1 block text-xs font-normal text-slate-500">Minimum 12 characters.</span>
      <?php if (!empty($errors['password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['password']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Confirm new password *
      <input type="password" name="confirm_password" minlength="12" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['confirm_password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['confirm_password']) ?></span><?php endif; ?>
    </label>

    <div class="flex gap-3">
      <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Update password</button>
      <a href="users.php" class="rounded-lg border bg-white px-6 py-3 font-semibold">Cancel</a>
    </div>
  </form>
</main>
</body></html>