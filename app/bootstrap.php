<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '') $_ENV[$key] = $value;
    }
}
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Africa/Nairobi');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('tonny_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function envv(string $key, string $default = ''): string {
    return (string)($_ENV[$key] ?? $default);
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . envv('DB_HOST', '127.0.0.1') . ';port=' . envv('DB_PORT', '3306') .
        ';dbname=' . envv('DB_DATABASE', 'tonny_plumbing') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, envv('DB_USERNAME', 'root'), envv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419); exit('Your session expired. Please go back and try again.');
    }
}
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) redirect('login.php');
}
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) { $_SESSION['_flash'][$key] = $message; return null; }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}
function log_event(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void {
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, ip_address, details) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$_SESSION['admin_id'] ?? null, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($details)]);
    } catch (Throwable $ignored) { /* logging must not break the main action */ }
}


require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/mpesa.php';
require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/animations.php';