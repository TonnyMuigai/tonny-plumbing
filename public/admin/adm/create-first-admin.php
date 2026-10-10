<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$pageError = null;
$pageSuccess = null;

try {
    $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count > 0) {
        http_response_code(403);
        exit('Setup is locked because an admin account already exists. Delete create-first-admin.php from your server.');
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Could not connect to the database or find the admin_users table. Check your .env settings and import database/schema.sql.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($name === '' || mb_strlen($name) > 120) {
        $pageError = 'Enter your name (up to 120 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $pageError = 'Enter a valid email address.';
    } elseif (strlen($password) < 12) {
        $pageError = 'Use a password with at least 12 characters.';
    } elseif ($password !== $confirm) {
        $pageError = 'The passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO admin_users (name,email,password_hash,role,is_active) VALUES (?,?,?,\'admin\',1)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            log_event('admin.first_admin_created', 'admin_user', (int)db()->lastInsertId());
            $pageSuccess = 'Admin account created. Delete this setup file now, then sign in using your new credentials.';
        } catch (PDOException $exception) {
            $pageError = 'Could not create the account. That email may already be in use. Check the email and try again.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create first admin | TONNY</title><script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-5 flex items-center justify-center">
<main class="w-full max-w-lg rounded-2xl bg-white p-7 shadow">
<a href="../index.php" class="font-black text-teal-700">TONNY.</a>
<h1 class="mt-5 text-2xl font-black text-slate-900">Create the first admin</h1>
<p class="mt-2 text-sm text-slate-600">This one-time setup page only works while the admin user table is empty.</p>
<?php if ($pageError): ?><p role="alert" class="mt-4 rounded-lg bg-rose-100 p-3 text-sm text-rose-800"><?= e($pageError) ?></p><?php endif; ?>
<?php if ($pageSuccess): ?>
<div class="mt-4 rounded-lg bg-emerald-100 p-4 text-sm text-emerald-900">
<p><?= e($pageSuccess) ?></p>
<p class="mt-3"><a class="font-bold underline" href="login.php">Go to admin login</a></p>
</div>
<?php else: ?>
<form method="post" class="mt-5 space-y-4">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label class="block text-sm font-semibold">Full name
<input name="name" maxlength="120" required autocomplete="name" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
</label>
<label class="block text-sm font-semibold">Admin email
<input type="email" name="email" maxlength="190" required autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
</label>
<label class="block text-sm font-semibold">Password (minimum 12 characters)
<input type="password" name="password" minlength="12" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
</label>
<label class="block text-sm font-semibold">Confirm password
<input type="password" name="confirm_password" minlength="12" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal">
</label>
<button class="w-full rounded-xl bg-slate-900 p-3 font-bold text-white hover:bg-slate-700">Create admin account</button>
</form>
<?php endif; ?>
<p class="mt-5 text-xs text-slate-500">Security: after account creation, remove <code>create-first-admin.php</code> from <code>public/admin</code>.</p>
</main>
</body></html>
