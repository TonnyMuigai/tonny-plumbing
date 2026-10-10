<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super_admin();

if (!can_create_more_accounts()) {
    flash('error', 'Account limit reached (' . MAX_NON_SUPER_ACCOUNTS . '). Delete an account first.');
    redirect('users.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'role' => ROLE_STAFF];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']  = trim((string)($_POST['name'] ?? ''));
    $old['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['role']  = (string)($_POST['role'] ?? ROLE_STAFF);
    $password     = (string)($_POST['password'] ?? '');
    $confirm      = (string)($_POST['confirm_password'] ?? '');

    if ($old['name'] === '' || mb_strlen($old['name']) > 120) $errors['name'] = 'Name is required (max 120 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors['email'] = 'Enter a valid email address.';
    if (!in_array($old['role'], [ROLE_ADMIN, ROLE_STAFF], true)) $errors['role'] = 'Choose Admin or Staff.';
    if (strlen($password) < 12) $errors['password'] = 'Password must be at least 12 characters.';
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';

    if (!$errors) {
        try {
            $stmt = db()->prepare('INSERT INTO admin_users (name, email, password_hash, role, is_active) VALUES (?,?,?,?,1)');
            $stmt->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['role']]);
            $newId = (int)db()->lastInsertId();
            log_event('user.created', 'admin_user', $newId, ['email' => $old['email'], 'role' => $old['role']]);
            flash('success', 'User created successfully.');
            redirect('users.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors['email'] = 'That email is already in use.';
            } else {
                error_log('user-add failed: ' . $ex->getMessage());
                $errors['_form'] = 'Could not create the user. Please try again.';
            }
        }
    }
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add user | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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

<main class="mx-auto max-w-3xl px-5 py-8">
  <a href="users.php" class="text-sm font-semibold text-brand-accent">← Back to users</a>
  <h1 class="mt-3 text-3xl font-black">Add admin user</h1>
  <p class="mt-2 text-slate-500">
    <?= non_super_account_count() ?> of <?= MAX_NON_SUPER_ACCOUNTS ?> admin/staff accounts used.
  </p>

  <?php if (!empty($errors['_form'])): ?>
    <p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-800"><?= e($errors['_form']) ?></p>
  <?php endif; ?>

  <form method="post" class="mt-6 space-y-5 rounded-2xl bg-white p-6 shadow-sm md:p-8">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label class="block text-sm font-semibold">Full name *
      <input name="name" maxlength="120" required value="<?= e($old['name']) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['name'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Email *
      <input type="email" name="email" maxlength="190" required value="<?= e($old['email']) ?>" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
      <?php if (!empty($errors['email'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['email']) ?></span><?php endif; ?>
    </label>

    <label class="block text-sm font-semibold">Role *
      <select name="role" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 font-normal">
        <option value="staff" <?= $old['role'] === 'staff' ? 'selected' : '' ?>>Staff — bookings, orders, products only</option>
        <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Admin — full business access incl. settings & reports</option>
      </select>
      <?php if (!empty($errors['role'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['role']) ?></span><?php endif; ?>
    </label>

    <div class="grid gap-5 sm:grid-cols-2">
      <label class="block text-sm font-semibold">Password *
        <input type="password" name="password" minlength="12" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <span class="mt-1 block text-xs font-normal text-slate-500">Minimum 12 characters.</span>
        <?php if (!empty($errors['password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['password']) ?></span><?php endif; ?>
      </label>
      <label class="block text-sm font-semibold">Confirm password *
        <input type="password" name="confirm_password" minlength="12" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
        <?php if (!empty($errors['confirm_password'])): ?><span class="mt-1 block text-xs font-normal text-rose-700"><?= e($errors['confirm_password']) ?></span><?php endif; ?>
      </label>
    </div>

    <div class="flex gap-3">
      <button class="rounded-lg bg-brand-primary px-6 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">Create user</button>
      <a href="users.php" class="rounded-lg border bg-white px-6 py-3 font-semibold">Cancel</a>
    </div>
  </form>
</main>
</body></html>