<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { flash('error', 'User not found.'); redirect('users.php'); }

$stmt = db()->prepare('SELECT id, name, email, role, is_active FROM admin_users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { flash('error', 'User not found.'); redirect('users.php'); }
if ($user['role'] === ROLE_SUPER_ADMIN) {
    flash('error', 'The super admin account cannot be edited here.');
    redirect('users.php');
}

$errors = [];
$old = ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']  = trim((string)($_POST['name'] ?? ''));
    $old['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['role']  = (string)($_POST['role'] ?? ROLE_STAFF);

    if ($old['name'] === '' || mb_strlen($old['name']) > 120) $errors['name'] = 'Name is required (max 120 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors['email'] = 'Enter a valid email address.';
    if (!in_array($old['role'], [ROLE_ADMIN, ROLE_STAFF], true)) $errors['role'] = 'Choose Admin or Staff.';

    if (!$errors) {
        try {
            $stmt = db()->prepare('UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?');
            $stmt->execute([$old['name'], $old['email'], $old['role'], $id]);
            log_event('user.updated', 'admin_user', $id, ['email' => $old['email'], 'role' => $old['role']]);
            flash('success', 'User updated.');
            redirect('users.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors['email'] = 'That email is already used by another account.';
            } else {
                error_log('user-edit failed: ' . $ex->getMessage());
                $errors['_form'] = 'Could not save changes. Please try again.';
            }
        }
    }
    $user = array_merge($user, $old);
}
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit user | <?= e(setting('admin_brand_name','TONNY Admin')) ?></title>
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
  <h1 class="mt-3 text-3xl font-black">Edit user</h1>

 