<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();
log_event('admin.logout','admin_user',isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
redirect('login.php');
