<?php
declare(strict_types=1);

/**
 * Load all settings once per request, cache in memory.
 * Usage: setting('site_name'), setting('whatsapp_number', '254723777601')
 */
function settings_all(bool $forceReload = false): array
{
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }
    $cache = [];
    try {
        $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        foreach ($rows as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Throwable $ex) {
        error_log('settings_all failed: ' . $ex->getMessage());
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    $val = $all[$key] ?? null;
    return ($val === null || $val === '') ? $default : (string)$val;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
    settings_all(true);
}

function setting_delete(string $key): void
{
    db()->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$key]);
    settings_all(true);
}

/**
 * Handle a branding image upload (logo/favicon/hero background).
 * Stores in public/uploads/branding/ and returns the relative path,
 * or null if no file was uploaded. Throws RuntimeException on validation failure.
 */
function handle_branding_upload(array $file, ?string $existingPath = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than 2 MB.');
    }

    $allowed = [
        'image/jpeg'     => 'jpg',
        'image/png'      => 'png',
        'image/webp'     => 'webp',
        'image/svg+xml'  => 'svg',
        'image/x-icon'   => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, SVG or ICO images are allowed.');
    }
    $ext = $allowed[$mime];

    $uploadDir = dirname(__DIR__) . '/public/uploads/branding';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($file['name'], PATHINFO_FILENAME)));
    $base = trim($base, '-') ?: 'brand';
    $filename = $base . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded file. Check that public/uploads/branding is writable.');
    }

    if ($existingPath !== null && $existingPath !== '') {
        $oldFile = dirname(__DIR__) . '/public/' . ltrim($existingPath, '/');
        $realUploads = realpath($uploadDir) ?: '';
        $realOld     = realpath($oldFile) ?: '';
        if ($realOld !== '' && $realUploads !== '' && str_starts_with($realOld, $realUploads)) {
            @unlink($realOld);
        }
    }

    return 'uploads/branding/' . $filename;
}

/**
 * Render the site logo: image if uploaded, otherwise text.
 */
function render_logo(string $variant = 'public'): string
{
    $name   = setting('site_name', 'TONNY');
    $suffix = setting('site_name_suffix', '.');
    $img    = setting('logo_image_path');

    $classes = 'flex items-center gap-2 text-xl font-black tracking-wide';

    if ($img !== '') {
        $src = e($img);
        if ($variant === 'admin') {
            $src = e('../' . ltrim($img, '/'));
        }
        return '<a href="index.php" class="' . $classes . '">'
             . '<img src="' . $src . '" alt="' . e($name) . '" class="h-8 w-auto">'
             . '</a>';
    }

    return '<a href="index.php" class="' . $classes . '">'
         . e($name)
         . ($suffix !== '' ? '<span class="text-brand-accent">' . e($suffix) . '</span>' : '')
         . '</a>';
}