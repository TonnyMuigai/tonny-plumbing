<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super_admin();

// Handle activate / deactivate / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');
    if (!$id) { flash('error', 'Invalid user.'); redirect('users.php'); }

    // Load target
    $stmt = db()->prepare('SELECT id, name, email, role FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) { flash('error', 'User not found.'); redirect('users.php'); }

    // Never let anyone act on the super admin account (even another super admin)
    if ($target['role'] === ROLE_SUPER_ADMIN) {
        flash('error', 'The super admin account cannot be changed here.');
        redirect('users.php');
    }

    if ($action === 'activate' || $action === 'deactivate') {
        $new = $action === 'activate' ? 1 : 0;
        db()->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?')->execute([$new, $id]);
        log_event('user.' . $action, 'admin_user', $id, ['email' => $target['email']]);
        flash('success', 'User ' . $action . 'd.');
        redirect('users.php');
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
        log_event('user.deleted', 'admin_user', $id, ['email' => $target['email']]);
        flash('success', 'User deleted.');
        redirect('users.php');
    }
}

$rows = db()->query('SELECT id, name, email, role, is_active, last_login_at, created_at FROM admin_users ORDER BY
    FIELD(role, "super_admin", "admin", "staff"), name ASC')->fetchAll();

$notice = flash('success');
$error  = flash('error');
$count  = non_super_account_count();
$canAdd = can_create_more_accounts();
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Users | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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

<main class="mx-auto max-w-6xl px-5 py-8">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-3xl font-black">Admin users</h1>
      <p class="mt-2 text-slate-500">
        <?= $count ?> of <?= MAX_NON_SUPER_ACCOUNTS ?> admin/staff accounts used, plus 1 super admin.
      </p>
    </div>
    <?php if ($canAdd): ?>
      <a href="user-add.php" class="rounded-lg bg-brand-primary px-5 py-3 font-semibold text-brand-onPrimary hover:bg-brand-primaryHover">+ Add user</a>
    <?php else: ?>
      <span class="rounded-lg bg-slate-200 px-5 py-3 font-semibold text-slate-500" title="Delete a user first">Account limit reached</span>
    <?php endif; ?>
  </div>

  <?php if ($notice): ?><p class="mt-5 rounded-lg bg-emerald-100 p-3 text-emerald-900"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-900"><?= e($error) ?></p><?php endif; ?>

  <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr>
            <th class="p-4">Name</th>
            <th class="p-4">Email</th>
            <th class="p-4">Role</th>
            <th class="p-4">Status</th>
            <th class="p-4">Last login</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $u): $isSuper = $u['role'] === ROLE_SUPER_ADMIN; ?>
            <tr class="border-t align-top <?= $isSuper ? 'bg-amber-50/60' : '' ?>">
              <td class="p-4 font-semibold"><?= e($u['name']) ?></td>
              <td class="p-4 text-slate-600"><?= e($u['email']) ?></td>
              <td class="p-4">
                <?php if ($isSuper): ?>
                  <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Super admin</span>
                <?php elseif ($u['role'] === ROLE_ADMIN): ?>
                  <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">Admin</span>
                <?php else: ?>
                  <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700">Staff</span>
                <?php endif; ?>
              </td>
              <td class="p-4">
                <?php if ((int)$u['is_active'] === 1): ?>
                  <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Active</span>
                <?php else: ?>
                  <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="p-4 text-xs text-slate-500"><?= e($u['last_login_at'] ?: '—') ?></td>
              <td class="p-4 text-right">
                <?php if ($isSuper): ?>
                  <span class="text-xs text-slate-400 italic">Protected</span>
                <?php else: ?>
                  <a href="user-edit.php?id=<?= (int)$u['id'] ?>" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Edit</a>
                  <a href="user-password.php?id=<?= (int)$u['id'] ?>" class="ml-1 rounded-md border px-3 py-1.5 text-xs font-semibold">Password</a>
                  <form method="post" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="action" value="<?= (int)$u['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                    <button onclick="return confirm('<?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> <?= e($u['name']) ?>?')" class="ml-1 rounded-md border px-3 py-1.5 text-xs font-semibold"><?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                  <form method="post" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button onclick="return confirm('Permanently delete <?= e($u['name']) ?>? This cannot be undone.')" class="ml-1 rounded-md border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body></html>