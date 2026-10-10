<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace'>";
echo "DB values loaded by setting():\n";
echo "  color_primary: " . setting('color_primary', 'EMPTY') . "\n";
echo "  color_accent:  " . setting('color_accent',  'EMPTY') . "\n";
echo "  color_cta:     " . setting('color_cta',     'EMPTY') . "\n\n";
echo "Raw settings table:\n";
foreach (db()->query('SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE "color%"')->fetchAll() as $r) {
    echo "  {$r['setting_key']} = {$r['setting_value']}\n";
}
echo "\nrender_brand_head() output:\n";
echo htmlspecialchars(render_brand_head());
echo "</pre>";