<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

echo "Before:\n";
echo "  color_accent = " . setting('color_accent', 'EMPTY') . "\n\n";

setting_set('color_accent', '#ff00ff');

echo "After setting to #ff00ff:\n";
echo "  color_accent = " . setting('color_accent', 'EMPTY') . "\n\n";

echo "Straight from DB:\n";
$row = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'color_accent'")->fetch();
echo "  color_accent = " . ($row['setting_value'] ?? 'NOT FOUND') . "\n";