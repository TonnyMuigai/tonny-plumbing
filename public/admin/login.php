<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// If already logged in, verify the account still exists, is active,
// and refresh name + role from DB (in case super admin changed them).
if (!empty($_SESSION['admin_id'])) {
    try {
        $stmt = db()->prepare('SELECT id, name, role, is_active FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['is_active'] === 1) {
            $_SESSION['admin_name'] = $row['name'];
            $_SESSION['admin_role'] = $row['role'];
            redirect('index.php');
        }
    } catch (Throwable $ex) {
        // fall through to login form
    }
    // Account gone or deactivated — clear session
    $_SESSION = [];
    session_destroy();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Email or password is incorrect.';
    } elseif ((int)$user['is_active'] !== 1) {
        $error = 'This account has been deactivated. Contact your super admin.';
    } elseif (!password_verify($password, $user['password_hash'])) {
        $error = 'Email or password is incorrect.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int)$user['id'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_role'] = $user['role'];   // fresh from DB
        db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        log_event('admin.login', 'admin_user', (int)$user['id'], ['role' => $user['role']]);
        redirect('index.php');
    }
}

$siteName = setting('site_name', 'TONNY');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login | <?= e($siteName) ?></title>
<?= render_brand_head() ?>
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 p-5">
<form method="post" class="w-full max-w-md rounded-2xl bg-white p-8 shadow">
  <a href="../index.php" class="font-black text-brand-primary text-2xl"><?= e($siteName) ?><span class="text-brand-accent">.</span></a>
  <h1 class="mt-5 text-2xl font-black">Admin sign in</h1>
  <p class="mt-2 text-sm text-slate-500">Access bookings, orders and products.</p>

  <?php if ($error): ?>
    <p class="mt-4 rounded-lg bg-rose-100 p-3 text-sm text-rose-800"><?= e($error) ?></p>
  <?php endif; ?>

  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <label class="mt-5 block text-sm font-semibold">Email
    <input type="email" name="email" required autocomplete="username" class="mt-2 w-full rounded-lg border border-slate-300 p-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
  </label>

  <label class="mt-4 block text-sm font-semibold">Password
    <input type="password" name="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-300 p-3 font-normal focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/20">
  </label>

  <button class="mt-6 w-full rounded-xl bg-brand-primary px-3 py-3 font-bold text-brand-onPrimary hover:bg-brand-primaryHover">Sign in</button>
</form>
</body></html>